<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Employee */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $team = $this->currentTeam();

        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'email' => $this->user->email,
            'profile_image_path' => $this->profile_image_path,
            'photo_url' => $this->profile_image_path
                ? "/employees/{$this->id}/photo?v={$this->updated_at->timestamp}"
                : null,
            'phone' => $this->phone,
            'address' => $this->address,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'joining_date' => $this->joining_date->toDateString(),
            'designation' => $this->designation,
            'employment_type' => $this->employment_type->value,
            'status' => $this->status->value,
            'confirmation_date' => $this->confirmation_date?->toDateString(),
            'office_location' => $this->office_location,
            'timezone' => $this->timezone,
            'weekend_day' => $this->weekend_day?->value,
            'overtime_eligible' => $this->overtime_eligible,
            'department' => $team?->department ? [
                'id' => $team->department->id,
                'name' => $team->department->name,
            ] : null,
            'team' => $team ? ['id' => $team->id, 'name' => $team->name] : null,
            'current_shift' => $this->currentShiftAssignment?->shift ? [
                'id' => $this->currentShiftAssignment->shift->id,
                'name' => $this->currentShiftAssignment->shift->name,
            ] : null,
            'team_leader' => $team?->teamLeader ? [
                'id' => $team->teamLeader->id,
                'full_name' => $team->teamLeader->fullName(),
            ] : null,
            'operation_manager' => $team?->department?->operationManager ? [
                'id' => $team->department->operationManager->id,
                'full_name' => $team->department->operationManager->fullName(),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
