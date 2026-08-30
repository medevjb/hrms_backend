<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Models\UserRole;
use App\Services\SalaryService;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * docs/PRD.md §83 — the append-only audit trail and its read-only viewer.
 */
function auditUser(array $permissions): User
{
    $user = User::factory()->create();
    $role = Role::query()->firstOrCreate(['name' => 'Audit '.fake()->unique()->word()]);

    foreach ($permissions as $permission) {
        $perm = Permission::query()->firstOrCreate(['name' => $permission]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);

    return $user;
}

test('the audit viewer requires audit.view', function () {
    AuditLog::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())->getJson('/api/v1/audit-logs')->assertStatus(403);

    $this->actingAs(auditUser([PermissionName::AuditView->value]))
        ->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

test('audit rows are append-only', function () {
    $log = AuditLog::factory()->create();

    expect(fn () => $log->update(['reason' => 'tampered']))->toThrow(RuntimeException::class);
    expect(fn () => $log->delete())->toThrow(RuntimeException::class);
});

test('a salary change writes a SALARY_CHANGED audit entry', function () {
    SalaryComponent::factory()->basic()->create();
    $employee = Employee::factory()->create();
    $actor = User::factory()->create();
    $basicId = SalaryComponent::query()->where('code', 'BASIC')->value('id');

    app(SalaryService::class)->assign($employee, Carbon::parse('2026-01-01'), [$basicId => '30000'], null, $actor);

    $log = AuditLog::query()->where('action', AuditAction::SalaryChanged)->sole();
    expect($log->user_id)->toBe($actor->id)
        ->and($log->entity_type)->toBe(Employee::class)
        ->and($log->entity_id)->toBe($employee->id)
        ->and($log->new_data['gross_monthly'])->toBe('30000.0000');
});

test('the viewer filters by action', function () {
    AuditLog::factory()->create(['action' => AuditAction::SalaryChanged]);
    AuditLog::factory()->create(['action' => AuditAction::LeaveApproved]);

    $this->actingAs(auditUser([PermissionName::AuditView->value]))
        ->getJson('/api/v1/audit-logs?filter[action]=SALARY_CHANGED')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});
