<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_code' => 'EMP-'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'joining_date' => fake()->dateTimeBetween('-3 years', 'now'),
            'designation' => fake()->jobTitle(),
            'employment_type' => EmploymentType::FullTime,
            'status' => EmployeeStatus::Active,
            'overtime_eligible' => true,
        ];
    }

    public function invited(): static
    {
        return $this->state(['status' => EmployeeStatus::Invited]);
    }
}
