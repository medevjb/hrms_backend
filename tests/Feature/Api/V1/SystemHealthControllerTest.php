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

test('the health snapshot reports the framework facts and machinery status', function () {
    Storage::fake('local');
    Cache::put('scheduler:heartbeat', now()->toIso8601String());

    $user = User::factory()->create();
    $role = Role::query()->firstOrCreate(['name' => 'DevOps']);
    $perm = Permission::query()->firstOrCreate(['name' => PermissionName::SystemHealthView->value]);
    $role->permissions()->syncWithoutDetaching([$perm->id]);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::System]);

    $this->actingAs($user)->getJson('/api/v1/system/health')
        ->assertOk()
        ->assertJsonPath('data.environment', 'testing')
        ->assertJsonPath('data.database.status', 'ok')
        ->assertJsonPath('data.local_storage.status', 'ok')
        ->assertJsonPath('data.scheduler.status', 'ok');
});
