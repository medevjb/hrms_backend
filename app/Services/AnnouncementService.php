<?php

namespace App\Services;

use App\Enums\AnnouncementAudienceType;
use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementTargetType;
use App\Enums\EmployeeStatus;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Employee;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Notification;

/**
 * docs/PRD.md §57 — announcement create/publish/read. Publishing resolves
 * the audience_type into a concrete employee set and writes one
 * notification each (§80); the mail channel is layered on for the types an
 * employee "cannot afford to miss" (AnnouncementType::warrantsEmail()).
 */
class AnnouncementService
{
    private const ACTIVE_STATUSES = [
        EmployeeStatus::Active,
        EmployeeStatus::Probation,
        EmployeeStatus::NoticePeriod,
    ];

    /**
     * §57 — publish a draft: stamp it, resolve the audience, and notify.
     * Idempotent by state — re-publishing an already-published announcement
     * is a 409, never a second notification flood.
     */
    public function publish(Announcement $announcement, ?User $actor = null): Announcement
    {
        abort_if($announcement->isPublished(), 409, 'This announcement is already published.');
        abort_if(
            $announcement->status === AnnouncementStatus::Expired,
            409,
            'This announcement has expired and cannot be published.',
        );

        $announcement->status = AnnouncementStatus::Published;
        $announcement->published_at = Carbon::now();
        $announcement->save();

        $recipients = $this->resolveAudience($announcement)
            ->map(fn (Employee $employee) => $employee->user)
            ->filter()
            ->unique('id')
            ->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AnnouncementPublished($announcement));
        }

        return $announcement->fresh();
    }

    /**
     * §57 — the employees an announcement reaches. ALL is every active
     * employee; the other four each expand their announcement_targets.
     *
     * @return Collection<int, Employee>
     */
    public function resolveAudience(Announcement $announcement): Collection
    {
        $base = Employee::query()->whereIn('status', self::ACTIVE_STATUSES);

        if ($announcement->audience_type === AnnouncementAudienceType::All) {
            return $base->get();
        }

        $announcement->loadMissing('targets');

        /** @return BaseCollection<int, int> */
        $targetIds = fn (AnnouncementTargetType $type): BaseCollection => $announcement->targets
            ->where('target_type', $type)
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $employeeIds = match ($announcement->audience_type) {
            AnnouncementAudienceType::Department => $this->departmentEmployeeIds($targetIds(AnnouncementTargetType::Department)),
            AnnouncementAudienceType::Team => $this->teamEmployeeIds($targetIds(AnnouncementTargetType::Team)),
            AnnouncementAudienceType::Role => $this->roleEmployeeIds($targetIds(AnnouncementTargetType::Role)),
            AnnouncementAudienceType::Selected => $targetIds(AnnouncementTargetType::Employee),
            AnnouncementAudienceType::All => collect(),
        };

        return $base->whereIn('id', $employeeIds->all())->get();
    }

    /**
     * §57 — record that an employee opened an announcement; `acknowledge`
     * marks the explicit "I acknowledge" for EMERGENCY / POLICY posts.
     */
    public function markRead(Announcement $announcement, Employee $employee, bool $acknowledge): AnnouncementRead
    {
        $read = AnnouncementRead::query()->firstOrNew([
            'announcement_id' => $announcement->id,
            'employee_id' => $employee->id,
        ]);

        $read->read_at ??= Carbon::now();
        $read->acknowledged = $read->acknowledged || $acknowledge;
        $read->save();

        return $read;
    }

    /**
     * The daily sweep (PublishDueAnnouncementsCommand): publish drafts
     * whose scheduled publish_at has arrived, and expire published
     * announcements past their expires_at.
     *
     * @return array{published: int, expired: int}
     */
    public function runDueSweep(Carbon $now): array
    {
        $published = 0;

        Announcement::query()
            ->where('status', AnnouncementStatus::Draft)
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', $now)
            ->get()
            ->each(function (Announcement $announcement) use (&$published) {
                $this->publish($announcement);
                $published++;
            });

        $expired = Announcement::query()
            ->where('status', AnnouncementStatus::Published)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->update(['status' => AnnouncementStatus::Expired]);

        return ['published' => $published, 'expired' => $expired];
    }

    /**
     * §57 — the announcements an employee may see: published, unexpired,
     * and either addressed to everyone or to a group they belong to.
     * Display query for the employee-facing list; the policy still gates
     * the individual record.
     *
     * @return Builder<Announcement>
     */
    public function visibleTo(Employee $employee): Builder
    {
        $teamIds = TeamMember::query()
            ->where('employee_id', $employee->id)
            ->whereNull('ended_at')
            ->pluck('team_id');

        $departmentIds = Team::query()->whereIn('id', $teamIds)->pluck('department_id');
        $roleIds = $employee->user?->roleAssignments()->pluck('role_id') ?? collect();

        return Announcement::query()
            ->where('status', AnnouncementStatus::Published)
            ->where(function (Builder $query) use ($employee, $teamIds, $departmentIds, $roleIds) {
                $query->where('audience_type', AnnouncementAudienceType::All)
                    ->orWhereHas('targets', function (Builder $targets) use ($employee, $teamIds, $departmentIds, $roleIds) {
                        $targets->where(fn (Builder $t) => $t->where('target_type', AnnouncementTargetType::Employee)->where('target_id', $employee->id))
                            ->orWhere(fn (Builder $t) => $t->where('target_type', AnnouncementTargetType::Team)->whereIn('target_id', $teamIds))
                            ->orWhere(fn (Builder $t) => $t->where('target_type', AnnouncementTargetType::Department)->whereIn('target_id', $departmentIds))
                            ->orWhere(fn (Builder $t) => $t->where('target_type', AnnouncementTargetType::Role)->whereIn('target_id', $roleIds));
                    });
            });
    }

    /**
     * @param  BaseCollection<int, int>  $departmentIds
     * @return BaseCollection<int, int>
     */
    private function departmentEmployeeIds(BaseCollection $departmentIds): BaseCollection
    {
        $teamIds = Team::query()->whereIn('department_id', $departmentIds)->pluck('id');

        return $this->teamEmployeeIds($teamIds);
    }

    /**
     * @param  BaseCollection<int, int>  $teamIds
     * @return BaseCollection<int, int>
     */
    private function teamEmployeeIds(BaseCollection $teamIds): BaseCollection
    {
        return TeamMember::query()
            ->whereIn('team_id', $teamIds)
            ->whereNull('ended_at')
            ->pluck('employee_id')
            ->unique()
            ->values();
    }

    /**
     * @param  BaseCollection<int, int>  $roleIds
     * @return BaseCollection<int, int>
     */
    private function roleEmployeeIds(BaseCollection $roleIds): BaseCollection
    {
        return Employee::query()
            ->whereHas('user.roleAssignments', fn (Builder $query) => $query->whereIn('role_id', $roleIds))
            ->pluck('id')
            ->values();
    }
}
