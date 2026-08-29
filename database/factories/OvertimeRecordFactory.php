<?php

namespace Database\Factories;

use App\Enums\OvertimeApprovalStage;
use App\Enums\OvertimeStatus;
use App\Enums\OvertimeType;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OvertimeRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeRecord>
 */
class OvertimeRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $employee = Employee::factory();

        return [
            'employee_id' => $employee,
            'attendance_record_id' => AttendanceRecord::factory()->for($employee),
            'work_date' => now()->toDateString(),
            'type' => OvertimeType::Weekend,
            'worked_minutes' => 510,
            'full_day_minutes_used' => 480,
            'overtime_days' => 1,
            'status' => OvertimeStatus::PendingTeamLeader,
            'current_stage' => OvertimeApprovalStage::TeamLeader,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => OvertimeStatus::Approved,
            'current_stage' => null,
            'decided_at' => now(),
        ]);
    }

    public function rejectedForDuration(): static
    {
        return $this->state(fn () => [
            'worked_minutes' => 200,
            'overtime_days' => 0,
            'status' => OvertimeStatus::Rejected,
            'current_stage' => null,
            'rejection_reason' => 'Insufficient working duration (200m of 480m required).',
            'decided_at' => now(),
        ]);
    }
}
