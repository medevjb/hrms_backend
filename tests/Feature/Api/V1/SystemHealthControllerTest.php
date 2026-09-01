<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * docs/PRD.md §79 — the minimal technical health snapshot.
 */
test('the health snapshot requires system.health.view', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/v1/system/health')->assertStatus(403);
});

function systemHealthUser(): User
{
    $user = User::factory()->create();
    $role = Role::query()->firstOrCreate(['name' => 'DevOps']);
    $perm = Permission::query()->firstOrCreate(['name' => PermissionName::SystemHealthView->value]);
    $role->permissions()->syncWithoutDetaching([$perm->id]);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::System]);

    return $user;
}

test('the health snapshot reports the framework facts and machinery status', function () {
    Storage::fake('local');
    Cache::put('scheduler:heartbeat', now()->toIso8601String());
    Cache::put('queue:worker-heartbeat', now()->toIso8601String());

    $this->actingAs(systemHealthUser())->getJson('/api/v1/system/health')
        ->assertOk()
        ->assertJsonPath('data.environment', 'testing')
        ->assertJsonPath('data.database.status', 'ok')
        ->assertJsonPath('data.local_storage.status', 'ok')
        ->assertJsonPath('data.scheduler.status', 'ok')
        ->assertJsonPath('data.queue.worker.status', 'ok')
        // docs/PRD.md §79 "Recent Errors" — the 24h count is now part of the snapshot.
        ->assertJsonStructure(['data' => [
            'application_version', 'environment', 'laravel_version', 'php_version',
            'database', 'local_storage', 'scheduler',
            'queue' => ['pending_jobs', 'failed_jobs', 'worker' => ['status', 'last_heartbeat']],
            'errors_24h', 'checked_at',
        ]]);
});

test('the queue worker reads as unknown until a heartbeat job runs, then stale when old', function () {
    Storage::fake('local');
    $user = systemHealthUser();

    $this->actingAs($user)->getJson('/api/v1/system/health')
        ->assertJsonPath('data.queue.worker.status', 'unknown');

    Cache::put('queue:worker-heartbeat', now()->subMinutes(30)->toIso8601String());

    $this->actingAs($user)->getJson('/api/v1/system/health')
        ->assertJsonPath('data.queue.worker.status', 'error');
});
