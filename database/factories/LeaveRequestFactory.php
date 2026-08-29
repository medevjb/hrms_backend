<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addWeek()->startOfDay();

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $start,
            'end_date' => $start,
            'days_requested' => 1,
            'status' => 'SUBMITTED',
            'current_stage' => 'TEAM_LEADER',
            'required_stages' => ['TEAM_LEADER', 'OPERATION_MANAGER', 'HR'],
            'submitted_at' => now(),
        ];
    }
}
