<?php

use App\Enums\Scope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Employees invited before EmployeeService::assignBaselineRole() existed
     * have a paired user with no role assignment at all, so self-service
     * (leave requests, own attendance calendar, payslips) is invisible to
     * them. Give every such user the Team Member role over their own
     * records — the same grant a fresh invite now gets.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'Team Member')->value('id');

        if ($roleId === null) {
            return;
        }

        $userIds = DB::table('users')
            ->join('employees', 'employees.user_id', '=', 'users.id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('user_roles')
                    ->whereColumn('user_roles.user_id', 'users.id');
            })
            ->pluck('users.id');

        $now = now();

        foreach ($userIds as $userId) {
            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'scope' => Scope::Self->value,
                'scope_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // A one-way data backfill — no safe automatic reversal.
    }
};
