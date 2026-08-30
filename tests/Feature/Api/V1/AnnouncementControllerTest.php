<?php

use App\Enums\AnnouncementStatus;
use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\AnnouncementPublished;
use Illuminate\Support\Facades\Notification;

function annUser(array $permissions, ?Employee $employee = null): User
{
    $user = $employee?->user ?? User::factory()->create();
    $role = Role::query()->firstOrCreate(['name' => 'Role '.fake()->unique()->word()]);

    foreach ($permissions as $permission) {
        $perm = Permission::query()->firstOrCreate(['name' => $permission]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);

    return $user;
}

beforeEach(fn () => Notification::fake());

test('creating an announcement requires announcement.create and starts as a draft', function () {
    $creator = annUser([PermissionName::AnnouncementCreate->value]);

    $this->actingAs(annUser([PermissionName::AnnouncementView->value]))
        ->postJson('/api/v1/announcements', [
            'type' => 'GENERAL', 'title' => 'x', 'content' => 'y', 'audience_type' => 'ALL',
        ])->assertStatus(403);

    $response = $this->actingAs($creator)->postJson('/api/v1/announcements', [
        'type' => 'POLICY',
        'title' => 'Updated leave policy',
        'content' => 'Please review the updated policy.',
        'audience_type' => 'ALL',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'DRAFT')
        ->assertJsonPath('data.acknowledgement_required', true); // POLICY defaults on
});

test('targets must exist and match the audience type', function () {
    $creator = annUser([PermissionName::AnnouncementCreate->value]);

    $this->actingAs($creator)->postJson('/api/v1/announcements', [
        'type' => 'TEAM', 'title' => 't', 'content' => 'c', 'audience_type' => 'TEAM', 'targets' => [999999],
    ])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');

    $team = Team::factory()->create();
    $this->actingAs($creator)->postJson('/api/v1/announcements', [
        'type' => 'TEAM', 'title' => 't', 'content' => 'c', 'audience_type' => 'TEAM', 'targets' => [$team->id],
    ])->assertStatus(201);
});

test('publishing requires announcement.publish and notifies the audience', function () {
    Employee::factory()->count(2)->create();
    $announcement = Announcement::factory()->create();

    $this->actingAs(annUser([PermissionName::AnnouncementCreate->value]))
        ->postJson("/api/v1/announcements/{$announcement->id}/publish")
        ->assertStatus(403);

    $this->actingAs(annUser([PermissionName::AnnouncementPublish->value]))
        ->postJson("/api/v1/announcements/{$announcement->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'PUBLISHED');

    Notification::assertSentTimes(AnnouncementPublished::class, 2);
});

test('a draft is invisible to its future audience but a published one is not', function () {
    $employee = Employee::factory()->create();
    $viewer = annUser([PermissionName::AnnouncementView->value], $employee);

    $draft = Announcement::factory()->create();
    $this->actingAs($viewer)->getJson('/api/v1/announcements')->assertOk()->assertJsonPath('meta.total', 0);

    $draft->update(['status' => AnnouncementStatus::Published, 'published_at' => now()]);
    $this->actingAs($viewer)->getJson('/api/v1/announcements')->assertOk()->assertJsonPath('meta.total', 1);
});

test('an employee only sees announcements targeted at a group they belong to', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $employee = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $employee->id]);
    $viewer = annUser([PermissionName::AnnouncementView->value], $employee);

    $mine = Announcement::factory()->published()->create(['audience_type' => 'TEAM']);
    $mine->targets()->create(['target_type' => 'TEAM', 'target_id' => $team->id]);
    $theirs = Announcement::factory()->published()->create(['audience_type' => 'TEAM']);
    $theirs->targets()->create(['target_type' => 'TEAM', 'target_id' => $otherTeam->id]);

    $this->actingAs($viewer)->getJson('/api/v1/announcements')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $mine->id);
});

test('an employee can mark an announcement read and acknowledge it', function () {
    $employee = Employee::factory()->create();
    $viewer = annUser([PermissionName::AnnouncementView->value], $employee);
    $announcement = Announcement::factory()->published()->create();

    $this->actingAs($viewer)
        ->postJson("/api/v1/announcements/{$announcement->id}/read", ['acknowledge' => true])
        ->assertOk()
        ->assertJsonPath('data.my_read.acknowledged', true);

    expect($announcement->reads()->where('employee_id', $employee->id)->where('acknowledged', true)->exists())->toBeTrue();
});

test('a draft cannot be marked read', function () {
    $employee = Employee::factory()->create();
    $viewer = annUser([PermissionName::AnnouncementView->value], $employee);
    $announcement = Announcement::factory()->create();

    $this->actingAs($viewer)->postJson("/api/v1/announcements/{$announcement->id}/read")->assertStatus(404);
});

test('only draft announcements can be edited', function () {
    $creator = annUser([PermissionName::AnnouncementCreate->value]);
    $published = Announcement::factory()->published()->create();

    $this->actingAs($creator)
        ->putJson("/api/v1/announcements/{$published->id}", ['title' => 'new'])
        ->assertStatus(403);
});
