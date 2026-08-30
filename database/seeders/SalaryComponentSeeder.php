<?php

namespace Database\Seeders;

use App\Enums\SalaryComponentType;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

/**
 * docs/PRD.md §59 — the V1 salary component catalogue. HR may add more
 * through the API; these are the ones §59 names.
 */
class SalaryComponentSeeder extends Seeder
{
    /** @var list<array{code: string, name: string, type: SalaryComponentType}> */
    private const COMPONENTS = [
        ['code' => 'BASIC', 'name' => 'Basic Salary', 'type' => SalaryComponentType::Basic],
        ['code' => 'HOUSING', 'name' => 'Housing Allowance', 'type' => SalaryComponentType::Allowance],
        ['code' => 'MEDICAL', 'name' => 'Medical Allowance', 'type' => SalaryComponentType::Allowance],
        ['code' => 'TRANSPORT', 'name' => 'Transport Allowance', 'type' => SalaryComponentType::Allowance],
        ['code' => 'OTHER', 'name' => 'Other Allowance', 'type' => SalaryComponentType::Allowance],
    ];

    public function run(): void
    {
        foreach (self::COMPONENTS as $index => $component) {
            SalaryComponent::query()->updateOrCreate(
                ['code' => $component['code']],
                [
                    'name' => $component['name'],
                    'type' => $component['type'],
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }
}
