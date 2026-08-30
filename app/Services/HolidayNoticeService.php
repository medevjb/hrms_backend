<?php

namespace App\Services;

use App\Enums\AnnouncementAudienceType;
use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Enums\HolidayNoticeStatus;
use App\Enums\HolidayReminderStatus;
use App\Enums\PermissionName;
use App\Models\Announcement;
use App\Models\Holiday;
use App\Models\HolidayNotice;
use App\Models\HolidayReminder;
use App\Models\OrganizationSettings;
use App\Models\User;
use App\Notifications\HolidayNoticeAwaitingApproval;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * docs/PRD.md §55/§56 — the five-day holiday reminder and the notice it
 * produces. The scan drafts a notice PENDING_APPROVAL and pings Head HR;
 * nothing is published until Head HR signs it (§55). Approval renders the
 * §56 PDF into private storage (§82) and publishes a HOLIDAY announcement,
 * which is what actually emails every employee (§80).
 */
class HolidayNoticeService
{
    /**
     * §55 — Laravel Scheduler calls this once a day. Any active holiday
     * exactly `lead_days` away with no reminder yet gets one, plus a
     * PENDING_APPROVAL notice draft and a notification to Head HR. The
     * unique holiday_id on holiday_reminders makes a re-run across the
     * five in-window days a no-op.
     */
    public function scanForUpcomingHolidays(Carbon $today, int $leadDays = 5): int
    {
        $targetDate = $today->copy()->startOfDay()->addDays($leadDays)->toDateString();

        $holidays = Holiday::query()
            ->where('active', true)
            ->whereDate('date', $targetDate)
            ->whereDoesntHave('reminder')
            ->get();

        foreach ($holidays as $holiday) {
            $this->openReminder($holiday, $today, $leadDays);
        }

        return $holidays->count();
    }

    /**
     * §55/§56 — Head HR signs off. Applies any edits, renders and stores
     * the PDF, flips the notice to PUBLISHED, actions the reminder, and
     * publishes the linked HOLIDAY announcement to every employee.
     */
    public function approve(
        HolidayNotice $notice,
        User $approver,
        ?string $message = null,
        ?string $closureNote = null,
        ?CarbonInterface $returnDate = null,
    ): HolidayNotice {
        abort_if(
            $notice->status !== HolidayNoticeStatus::PendingApproval,
            409,
            'This holiday notice is not awaiting approval.',
        );

        $notice->fill(array_filter([
            'message' => $message,
            'closure_note' => $closureNote,
            'return_date' => $returnDate?->toDateString(),
        ], fn ($value) => $value !== null));

        $notice->signatory_name = $approver->name;
        $notice->signatory_user_id = $approver->id;
        $notice->generated_at = Carbon::now();
        $notice->status = HolidayNoticeStatus::Published;
        $notice->file_path = $this->renderPdf($notice->loadMissing('holiday'));
        $notice->save();

        $notice->reminder?->update([
            'status' => HolidayReminderStatus::Actioned,
            'actioned_by_user_id' => $approver->id,
            'actioned_at' => Carbon::now(),
        ]);

        $announcement = $this->publishAnnouncementFor($notice, $approver);
        $notice->announcement_id = $announcement->id;
        $notice->save();

        return $notice->fresh(['holiday', 'announcement', 'signatory']);
    }

    /**
     * §55 — Head HR decides the closure doesn't warrant a company-wide
     * notice. The reminder is closed so the scan won't reopen it.
     */
    public function dismiss(HolidayNotice $notice, User $actor): HolidayNotice
    {
        abort_if(
            $notice->status !== HolidayNoticeStatus::PendingApproval,
            409,
            'This holiday notice is not awaiting approval.',
        );

        $notice->update(['status' => HolidayNoticeStatus::Dismissed]);
        $notice->reminder?->update([
            'status' => HolidayReminderStatus::Dismissed,
            'actioned_by_user_id' => $actor->id,
            'actioned_at' => Carbon::now(),
        ]);

        return $notice->fresh();
    }

    private function openReminder(Holiday $holiday, Carbon $today, int $leadDays): void
    {
        $reminder = HolidayReminder::query()->create([
            'holiday_id' => $holiday->id,
            'lead_days_used' => $leadDays,
            'triggered_on' => $today->toDateString(),
        ]);

        $notice = HolidayNotice::query()->create([
            'holiday_id' => $holiday->id,
            'holiday_reminder_id' => $reminder->id,
            'reference' => $this->nextReference($holiday->date),
            'title' => "Office closure notice — {$holiday->title}",
            'message' => $this->draftMessage($holiday),
            'return_date' => $holiday->date->copy()->addDay()->toDateString(),
        ]);

        $recipients = $this->headHrRecipients();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new HolidayNoticeAwaitingApproval($notice));
            $reminder->update(['head_hr_notified_at' => Carbon::now()]);
        }
    }

    private function publishAnnouncementFor(HolidayNotice $notice, User $approver): Announcement
    {
        $holiday = $notice->holiday;

        $announcement = Announcement::query()->create([
            'type' => AnnouncementType::Holiday,
            'title' => $notice->title,
            'content' => $notice->message,
            'audience_type' => AnnouncementAudienceType::All,
            'status' => AnnouncementStatus::Draft,
            'created_by_user_id' => $approver->id,
            'holiday_notice_id' => $notice->id,
        ]);

        return app(AnnouncementService::class)->publish($announcement, $approver);
    }

    private function draftMessage(Holiday $holiday): string
    {
        $settings = OrganizationSettings::current();
        $date = $holiday->date->isoFormat('dddd, D MMMM YYYY');
        $return = $holiday->date->copy()->addDay()->isoFormat('dddd, D MMMM YYYY');

        return "{$settings->company_name} will be closed on {$date} in observance of {$holiday->title}. "
            .'Normal operations resume on '."{$return}. "
            .'Please plan any time-sensitive work accordingly.';
    }

    private function renderPdf(HolidayNotice $notice): string
    {
        $settings = OrganizationSettings::current();

        $pdf = Pdf::loadView('pdf.holiday-notice', [
            'notice' => $notice,
            'holiday' => $notice->holiday,
            'settings' => $settings,
        ]);

        $path = "holiday-notices/{$notice->reference}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    private function nextReference(CarbonInterface $holidayDate): string
    {
        $year = $holidayDate->year;
        $sequence = HolidayNotice::query()->where('reference', 'like', "HN-{$year}-%")->count() + 1;

        return sprintf('HN-%d-%04d', $year, $sequence);
    }

    /**
     * @return Collection<int, User>
     */
    private function headHrRecipients(): Collection
    {
        return User::query()->get()->filter(
            fn (User $user) => $user->hasPermission(PermissionName::HolidayNoticeApprove),
        )->values();
    }
}
