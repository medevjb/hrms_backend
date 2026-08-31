<?php

namespace App\Models;

use App\Enums\MissingCheckoutPolicy;
use App\Enums\OvertimeDailySalaryBasis;
use App\Enums\OvertimeHourlyRateMode;
use App\Enums\SalaryDayCalculationMethod;
use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Singleton — exactly one row, whatever its id happens to be, read through
 * current(). Every field here is docs/PRD.md §85's list. Nothing on this
 * table may ever be hard-coded elsewhere in the application (§125) —
 * resolve it through here instead.
 *
 * @property int $id
 * @property string $company_name
 * @property string|null $company_logo_path
 * @property string $timezone
 * @property string $currency
 * @property int $currency_decimal_places
 * @property int $late_grace_minutes
 * @property array<int, string> $weekend_days
 * @property Weekday|null $default_weekend_day
 * @property int|null $default_shift_id
 * @property int|null $payroll_cutoff_day
 * @property SalaryDayCalculationMethod $salary_day_calculation_method
 * @property bool $overtime_enabled
 * @property bool $weekend_overtime_enabled
 * @property bool $holiday_overtime_enabled
 * @property bool $hourly_overtime_enabled
 * @property int $overtime_full_day_minutes
 * @property OvertimeDailySalaryBasis $overtime_daily_salary_basis
 * @property OvertimeHourlyRateMode $overtime_hourly_rate_mode
 * @property string|null $overtime_hourly_fixed_rate
 * @property string $overtime_hourly_multiplier
 * @property bool $auto_absent_enabled
 * @property MissingCheckoutPolicy $missing_checkout_policy
 * @property int|null $attendance_min_minutes_half_day
 * @property int $attendance_checkin_window_minutes
 * @property int $leave_year_start_month
 * @property int|null $leave_carry_forward_cap_days
 */
#[Fillable([
    'company_name', 'company_logo_path', 'timezone', 'currency', 'currency_decimal_places',
    'late_grace_minutes', 'weekend_days', 'default_weekend_day', 'default_shift_id',
    'payroll_cutoff_day', 'salary_day_calculation_method',
    'overtime_enabled', 'weekend_overtime_enabled', 'holiday_overtime_enabled',
    'hourly_overtime_enabled', 'overtime_full_day_minutes', 'overtime_daily_salary_basis',
    'overtime_hourly_rate_mode', 'overtime_hourly_fixed_rate', 'overtime_hourly_multiplier',
    'auto_absent_enabled', 'missing_checkout_policy', 'attendance_min_minutes_half_day',
    'attendance_checkin_window_minutes', 'leave_year_start_month', 'leave_carry_forward_cap_days',
])]
class OrganizationSettings extends Model
{
    private const CACHE_KEY = 'organization_settings';

    /**
     * Mirrors the migration's column defaults. Eloquent doesn't hydrate a
     * mass-assigned model's absent attributes from the database after
     * create() — without this, every field current() doesn't pass
     * explicitly (timezone, currency, overtime toggles, ...) would read
     * back as null on the same request instead of its real DB default.
     */
    protected $attributes = [
        'company_name' => 'Agency HRM',
        'timezone' => 'Asia/Dhaka',
        'currency' => 'USD',
        'currency_decimal_places' => 2,
        'late_grace_minutes' => 10,
        'default_weekend_day' => 'friday',
        'salary_day_calculation_method' => 'FIXED_30_DAYS',
        'overtime_enabled' => true,
        'weekend_overtime_enabled' => true,
        'holiday_overtime_enabled' => true,
        'hourly_overtime_enabled' => false,
        'overtime_full_day_minutes' => 480,
        'overtime_daily_salary_basis' => 'BASIC',
        'overtime_hourly_rate_mode' => 'SALARY_DERIVED',
        'overtime_hourly_multiplier' => 1.0,
        'auto_absent_enabled' => true,
        'missing_checkout_policy' => 'LEAVE_OPEN',
        'attendance_checkin_window_minutes' => 240, // §136 default
        'leave_year_start_month' => 1,
    ];

    protected function casts(): array
    {
        return [
            'weekend_days' => 'array',
            'default_weekend_day' => Weekday::class,
            'overtime_enabled' => 'boolean',
            'weekend_overtime_enabled' => 'boolean',
            'holiday_overtime_enabled' => 'boolean',
            'hourly_overtime_enabled' => 'boolean',
            'auto_absent_enabled' => 'boolean',
            'salary_day_calculation_method' => SalaryDayCalculationMethod::class,
            'overtime_daily_salary_basis' => OvertimeDailySalaryBasis::class,
            'overtime_hourly_rate_mode' => OvertimeHourlyRateMode::class,
            'missing_checkout_policy' => MissingCheckoutPolicy::class,
            'overtime_hourly_fixed_rate' => 'decimal:4',
            'overtime_hourly_multiplier' => 'decimal:2',
        ];
    }

    /**
     * The one settings row, created with its defaults on first access.
     * Cached — §125 implies this is read on every attendance/payroll
     * evaluation, not just in a settings screen.
     *
     * Deliberately does NOT do firstOrCreate(['id' => 1], ...): `id` isn't
     * fillable, so mass-assignment silently drops it from the create, and
     * the row gets whatever the table's real next auto-increment value is
     * — findable by that literal id only by coincidence. Find-or-create
     * against no constraint at all instead; there is only ever one row.
     */
    /**
     * Caches raw attributes, not the model object. A `rememberForever`
     * entry outlives any single deploy — caching the serialized object
     * itself means the next code change to this class (a new cast, a
     * renamed property) can leave an old deploy's object sitting in the
     * database/file cache store that no longer unserializes cleanly,
     * failing every request with __PHP_Incomplete_Class until someone
     * manually clears it. A plain attributes array has no class shape to
     * go stale — rehydrating through newFromBuilder() is exactly what a
     * fresh query() would produce. Found this the hard way against the
     * database cache driver.
     */
    public static function current(): self
    {
        $attributes = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => (static::query()->first() ?? static::query()->create([
                'timezone' => 'Asia/Dhaka', // §142 — authoritative for attendance
                'weekend_days' => ['friday'],
                'default_weekend_day' => 'friday', // §85 — one weekly off day
                'late_grace_minutes' => 10, // docs/PRD.md §101 default
                'hourly_overtime_enabled' => false, // §47 — OFF by default
            ]))->getAttributes(),
        );

        return static::query()->getModel()->newFromBuilder($attributes);
    }

    protected static function booted(): void
    {
        // §85's weekly off is one day now (docs/PRD.md §5683). `weekend_days`
        // stays as the stored list every reader already consults; the single
        // `default_weekend_day` accessor is kept in lockstep with it both
        // ways so writing either keeps the other honest.
        static::saving(function (self $settings): void {
            if ($settings->isDirty('default_weekend_day') && $settings->default_weekend_day !== null) {
                $settings->weekend_days = [$settings->default_weekend_day->value];
            } elseif ($settings->isDirty('weekend_days') && count($settings->weekend_days ?? []) === 1) {
                $settings->default_weekend_day = Weekday::tryFrom($settings->weekend_days[0]);
            }
        });

        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Is `$date` a weekly off day? The employee's own weekend day wins when
     * they have one; otherwise the organization weekend applies (docs/PRD.md
     * §85, §5683). The resolver signature is "(employee, date)" so nothing
     * downstream needs to know where the answer came from.
     */
    public function isWeekend(Carbon $date, ?Employee $employee = null): bool
    {
        if ($employee?->weekend_day !== null) {
            return $employee->weekend_day->matches($date);
        }

        return in_array(strtolower($date->englishDayOfWeek), $this->weekend_days, true);
    }
}
