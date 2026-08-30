<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * docs/PRD.md §66/§147 — the payroll toggles not covered by
 * OrganizationSettings (§85). Singleton, read through current(), mirroring
 * OrganizationSettings' pattern (raw-attributes cache so a stale deploy
 * can't leave an __PHP_Incomplete_Class in the cache store).
 *
 * @property int $id
 * @property bool $late_penalty_enabled
 * @property bool $absence_deduction_enabled
 * @property bool $unpaid_leave_deduction_enabled
 * @property bool $overtime_earnings_enabled
 * @property int $dispute_window_days
 */
#[Fillable([
    'late_penalty_enabled', 'absence_deduction_enabled', 'unpaid_leave_deduction_enabled',
    'overtime_earnings_enabled', 'dispute_window_days',
])]
class PayrollSettings extends Model
{
    private const CACHE_KEY = 'payroll_settings';

    protected $attributes = [
        'late_penalty_enabled' => true,
        'absence_deduction_enabled' => true,
        'unpaid_leave_deduction_enabled' => true,
        'overtime_earnings_enabled' => true,
        'dispute_window_days' => 7,
    ];

    protected function casts(): array
    {
        return [
            'late_penalty_enabled' => 'boolean',
            'absence_deduction_enabled' => 'boolean',
            'unpaid_leave_deduction_enabled' => 'boolean',
            'overtime_earnings_enabled' => 'boolean',
            'dispute_window_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        $attributes = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => (static::query()->first() ?? static::query()->create([]))->getAttributes(),
        );

        return static::query()->getModel()->newFromBuilder($attributes);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }
}
