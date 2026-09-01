<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

/**
 * A sensible starting leave catalogue (docs/PRD.md §35). `firstOrCreate`
 * on the code so a fresh install gets these, but a later re-seed never
 * clobbers whatever Head HR has since tuned.
 */
class LeaveTypeSeeder extends Seeder
{
    /** @var list<array<string, mixed>> */
    private const TYPES = [
        [
            'code' => 'ANNUAL', 'name' => 'Annual Leave', 'annual_allocation_days' => 20,
            'is_paid' => true, 'supports_half_day' => true,
            'carry_forward_enabled' => true, 'carry_forward_cap_days' => 10,
            'accrual_mode' => 'UPFRONT',
        ],
        [
            'code' => 'CASUAL', 'name' => 'Casual Leave', 'annual_allocation_days' => 10,
            'is_paid' => true, 'supports_half_day' => true,
            'carry_forward_enabled' => false, 'accrual_mode' => 'UPFRONT',
        ],
        [
            'code' => 'SICK', 'name' => 'Sick Leave', 'annual_allocation_days' => 14,
            'is_paid' => true, 'supports_half_day' => true,
            'carry_forward_enabled' => false, 'accrual_mode' => 'UPFRONT',
        ],
        [
            'code' => 'UNPAID', 'name' => 'Unpaid Leave', 'annual_allocation_days' => 0,
            'is_paid' => false, 'supports_half_day' => true,
            'carry_forward_enabled' => false, 'accrual_mode' => 'UPFRONT',
        ],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            LeaveType::query()->firstOrCreate(['code' => $type['code']], $type);
        }
    }
}
