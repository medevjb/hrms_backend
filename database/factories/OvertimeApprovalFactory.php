<?php

namespace Database\Factories;

use App\Enums\OvertimeApprovalDecision;
use App\Enums\OvertimeApprovalStage;
use App\Models\OvertimeApproval;
use App\Models\OvertimeRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeApproval>
 */
class OvertimeApprovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'overtime_record_id' => OvertimeRecord::factory(),
            'stage' => OvertimeApprovalStage::TeamLeader,
            'approver_user_id' => User::factory(),
            'decision' => OvertimeApprovalDecision::Approved,
            'decided_at' => now(),
        ];
    }
}
