<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user's own record for the personal settings screen — their
 * editable name/contact fields, plus the read-only employment context HR
 * owns. Distinct from UserResource (roles/permissions for the shell).
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

        return [
            'name' => $this->name,
            'email' => $this->email,
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'employee' => $employee === null ? null : [
                'employee_code' => $employee->employee_code,
                'designation' => $employee->designation,
                'employment_type' => $employee->employment_type,
                'status' => $employee->status,
                'joining_date' => $employee->joining_date->toDateString(),
                'department' => $department === null ? null : [
                    'id' => $department->id,
                    'name' => $department->name,
                ],
                'team' => $team === null ? null : [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'phone' => $employee->phone,
                'address' => $employee->address,
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_phone' => $employee->emergency_contact_phone,
            ],
        ];
    }
}
