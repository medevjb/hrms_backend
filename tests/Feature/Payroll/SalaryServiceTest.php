<?php

use App\Enums\SalaryDayCalculationMethod;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\SalaryService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * docs/PRD.md §59/§65 — effective-dated salary versions and daily-salary
 * derivation.
 */
function salaryComponents(): array
{
    return [
        'basic' => SalaryComponent::factory()->basic()->create(),
        'housing' => SalaryComponent::factory()->create(['code' => 'HOUSING', 'name' => 'Housing Allowance']),
    ];
}

function assignSalary(Employee $employee, string $effectiveFrom, string $basic, string $housing = '0'): void
{
    $components = SalaryComponent::query()->get()->keyBy('code');

    app(SalaryService::class)->assign(
        $employee,
        Carbon::parse($effectiveFrom),
        array_filter([
            $components['BASIC']->id => $basic,
            $components['HOUSING']->id => $housing,
        ]),
        null,
        User::factory()->create(),
    );
}

test('assigning a salary records basic and gross and stores each component', function () {
    salaryComponents();
    $employee = Employee::factory()->create();

    assignSalary($employee, '2026-01-01', '30000', '5000');

    $salary = $employee->currentSalary();
    expect($salary->basic_salary)->toBe('30000.0000')
        ->and($salary->gross_monthly)->toBe('35000.0000')
        ->and($salary->components)->toHaveCount(2);
});

test('a new version closes the previous one the day before it starts', function () {
    salaryComponents();
    $employee = Employee::factory()->create();

    assignSalary($employee, '2026-01-01', '30000');
    assignSalary($employee, '2026-06-01', '36000');

    $versions = $employee->salaries()->orderBy('effective_from')->get();
    expect($versions)->toHaveCount(2)
        ->and($versions[0]->ended_at->toDateString())->toBe('2026-05-31')
        ->and($versions[1]->ended_at)->toBeNull();
});

test('salaryOn returns the version in force on a date', function () {
    salaryComponents();
    $employee = Employee::factory()->create();
    assignSalary($employee, '2026-01-01', '30000');
    assignSalary($employee, '2026-06-01', '36000');

    $service = app(SalaryService::class);
    expect($service->salaryOn($employee, Carbon::parse('2026-03-15'))->basic_salary)->toBe('30000.0000')
        ->and($service->salaryOn($employee, Carbon::parse('2026-07-01'))->basic_salary)->toBe('36000.0000')
        ->and($service->salaryOn($employee, Carbon::parse('2025-12-31')))->toBeNull();
});

test('the effective date must be after the current version', function () {
    salaryComponents();
    $employee = Employee::factory()->create();
    assignSalary($employee, '2026-01-01', '30000');

    expect(fn () => assignSalary($employee, '2026-01-01', '31000'))
        ->toThrow(ValidationException::class);
});

test('a salary must include a non-zero basic component', function () {
    salaryComponents();
    $employee = Employee::factory()->create();
    $housing = SalaryComponent::query()->where('code', 'HOUSING')->first();

    expect(fn () => app(SalaryService::class)->assign(
        $employee, Carbon::parse('2026-01-01'), [$housing->id => '5000'], null, User::factory()->create(),
    ))->toThrow(ValidationException::class);
});

test('daily salary follows the period method', function () {
    salaryComponents();
    $employee = Employee::factory()->create();
    assignSalary($employee, '2026-01-01', '30000');
    $salary = $employee->currentSalary();
    $service = app(SalaryService::class);

    $fixed = PayrollPeriod::factory()->create([
        'label' => 'August 2026', 'start_date' => '2026-08-01', 'end_date' => '2026-08-31',
        'salary_day_calculation_method_used' => SalaryDayCalculationMethod::Fixed30Days,
    ]);
    $calendar = PayrollPeriod::factory()->create([
        'label' => 'February 2026', 'start_date' => '2026-02-01', 'end_date' => '2026-02-28',
        'salary_day_calculation_method_used' => SalaryDayCalculationMethod::CalendarDays,
    ]);

    expect($service->dailySalary($salary, $fixed))->toBe('1000.0000')
        ->and($service->dailySalary($salary, $calendar))->toBe('1071.4286'); // 30000 / 28
});
