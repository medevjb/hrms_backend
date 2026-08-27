<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeStatusHistory>
 */
class EmployeeStatusHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'from_status' => EmployeeStatus::Invited,
            'to_status' => EmployeeStatus::Active,
        ];
    }
}
