<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user's own record for the personal profile screen. Carries
 * everything about them that isn't confidential — the contact fields they
 * edit, plus all the employment context HR owns, read-only. Deliberately
 * omits salary, payroll, and documents (§82, §11 — employee.financial.*).
 *
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $employee = $this->employee;
        $team = $employee?->currentTeamMembership?->team;
        $department = $team?->department;
        $leader = $team?->teamLeader;
        $manager = $department?->operationManager;

        return [
            'name' => $this->name,
            'email' => $this->email,
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'photo_url' => $employee?->profile_image_path
                ? '/auth/profile/photo?v='.$employee->updated_at->timestamp
                : null,
            'employee' => $employee === null ? null : [
                'employee_code' => $employee->employee_code,
                'designation' => $employee->designation,
                'employment_type' => $employee->employment_type,
                'status' => $employee->status,
                'joining_date' => $employee->joining_date->toDateString(),
                'confirmation_date' => $employee->confirmation_date?->toDateString(),
                'office_location' => $employee->office_location,
                'timezone' => $employee->timezone,
                'weekend_day' => $employee->weekend_day?->value,
                'overtime_eligible' => $employee->overtime_eligible,
                'department' => $department === null ? null : [
                    'id' => $department->id,
                    'name' => $department->name,
                ],
                'team' => $team === null ? null : [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'current_shift' => $employee->currentShiftAssignment?->shift ? [
                    'id' => $employee->currentShiftAssignment->shift->id,
                    'name' => $employee->currentShiftAssignment->shift->name,
                ] : null,
                'team_leader' => $leader === null ? null : [
                    'id' => $leader->id,
                    'full_name' => $leader->fullName(),
                ],
                'operation_manager' => $manager === null ? null : [
                    'id' => $manager->id,
                    'full_name' => $manager->fullName(),
                ],
                'phone' => $employee->phone,
                'address' => $employee->address,
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_phone' => $employee->emergency_contact_phone,
            ],
        ];
    }
}
