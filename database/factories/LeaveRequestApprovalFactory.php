<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequestApproval>
 */
class LeaveRequestApprovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_request_id' => LeaveRequest::factory(),
            'stage' => 'TEAM_LEADER',
            'approver_user_id' => User::factory(),
            'decision' => 'APPROVED',
            'decided_at' => now(),
        ];
    }
}
