<?php

use App\Enums\AnnouncementAudienceType;
use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementTargetType;
use App\Enums\AnnouncementType;
use App\Enums\EmployeeStatus;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\UserRole;
use App\Notifications\AnnouncementPublished;
use App\Services\AnnouncementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * docs/PRD.md §57 — audience resolution, publish notifications, read
 * tracking, and the scheduled publish / expiry sweep.
 */
function annService(): AnnouncementService
{
    return app(AnnouncementService::class);
}

test('an ALL announcement resolves to every active employee', function () {
    Employee::factory()->count(2)->create();
    Employee::factory()->create(['status' => EmployeeStatus::Terminated]);

    $announcement = Announcement::factory()->create(['audience_type' => AnnouncementAudienceType::All]);

    expect(annService()->resolveAudience($announcement))->toHaveCount(2);
});

test('a TEAM announcement resolves to current members of the targeted teams', function () {
    $team = Team::factory()->create();
    $member = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $member->id]);
    $formerMember = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $formerMember->id, 'ended_at' => now()->subDay()]);
    Employee::factory()->create();

    $announcement = Announcement::factory()->create(['audience_type' => AnnouncementAudienceType::Team]);
    $announcement->targets()->create(['target_type' => AnnouncementTargetType::Team, 'target_id' => $team->id]);

    expect(annService()->resolveAudience($announcement)->pluck('id')->all())->toBe([$member->id]);
});

test('a DEPARTMENT announcement resolves across every team in the department', function () {
    $department = Department::factory()->create();
    $team = Team::factory()->create(['department_id' => $department->id]);
    $member = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $member->id]);

    $announcement = Announcement::factory()->create(['audience_type' => AnnouncementAudienceType::Department]);
    $announcement->targets()->create(['target_type' => AnnouncementTargetType::Department, 'target_id' => $department->id]);

    expect(annService()->resolveAudience($announcement)->pluck('id')->all())->toBe([$member->id]);
});

test('a ROLE announcement resolves to employees holding that role', function () {
    $role = Role::factory()->create();
    $member = Employee::factory()->create();
    UserRole::factory()->create(['user_id' => $member->user_id, 'role_id' => $role->id]);
    Employee::factory()->create();

    $announcement = Announcement::factory()->create(['audience_type' => AnnouncementAudienceType::Role]);
    $announcement->targets()->create(['target_type' => AnnouncementTargetType::Role, 'target_id' => $role->id]);

    expect(annService()->resolveAudience($announcement)->pluck('id')->all())->toBe([$member->id]);
});

test('publishing notifies each recipient once and stamps the announcement', function () {
    Notification::fake();
    $employees = Employee::factory()->count(3)->create();
    $announcement = Announcement::factory()->create(['audience_type' => AnnouncementAudienceType::All]);

    $published = annService()->publish($announcement);

    expect($published->status)->toBe(AnnouncementStatus::Published)
        ->and($published->published_at)->not->toBeNull();

    Notification::assertSentTimes(AnnouncementPublished::class, 3);
    $employees->each(fn (Employee $employee) => Notification::assertSentTo($employee->user, AnnouncementPublished::class));
});

test('publishing an already-published announcement is rejected', function () {
    $announcement = Announcement::factory()->published()->create();

    expect(fn () => annService()->publish($announcement))
        ->toThrow(HttpException::class);
});

test('a GENERAL announcement stays in-app only, a POLICY one adds email', function () {
    $general = Announcement::factory()->create(['type' => AnnouncementType::General]);
    $policy = Announcement::factory()->create(['type' => AnnouncementType::Policy, 'acknowledgement_required' => true]);
    $notifiable = Employee::factory()->create()->user;

    expect((new AnnouncementPublished($general))->via($notifiable))->toBe(['database'])
        ->and((new AnnouncementPublished($policy))->via($notifiable))->toBe(['database', 'mail']);
});

test('markRead is idempotent and only ever upgrades acknowledgement', function () {
    $employee = Employee::factory()->create();
    $announcement = Announcement::factory()->published()->create();

    annService()->markRead($announcement, $employee, false);
    $read = annService()->markRead($announcement, $employee, true);
    annService()->markRead($announcement, $employee, false);

    expect($announcement->reads()->count())->toBe(1)
        ->and($read->fresh()->acknowledged)->toBeTrue();
});

test('the due sweep publishes scheduled drafts and expires finished announcements', function () {
    Notification::fake();
    Employee::factory()->create();

    $due = Announcement::factory()->create([
        'audience_type' => AnnouncementAudienceType::All,
        'publish_at' => Carbon::now()->subHour(),
    ]);
    $future = Announcement::factory()->create(['publish_at' => Carbon::now()->addDay()]);
    $stale = Announcement::factory()->published()->create(['expires_at' => Carbon::now()->subHour()]);

    $result = annService()->runDueSweep(Carbon::now());

    expect($result)->toBe(['published' => 1, 'expired' => 1])
        ->and($due->fresh()->status)->toBe(AnnouncementStatus::Published)
        ->and($future->fresh()->status)->toBe(AnnouncementStatus::Draft)
        ->and($stale->fresh()->status)->toBe(AnnouncementStatus::Expired);
});
