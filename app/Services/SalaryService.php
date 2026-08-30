<?php

namespace App\Services;

use App\Enums\SalaryComponentType;
use App\Enums\SalaryDayCalculationMethod;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\OrganizationSettings;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/PRD.md §59/§65 — effective-dated employee salary and the §65 daily
 * salary derivation. Never overwrites history: a new version closes the
 * previous one with an `ended_at`.
 */
class SalaryService
{
    /**
     * @param  array<int, string>  $componentAmounts  salary_component_id => amount (string)
     *
     * @throws ValidationException
     */
    public function assign(
        Employee $employee,
        CarbonInterface $effectiveFrom,
        array $componentAmounts,
        ?string $note,
        User $actor,
    ): EmployeeSalary {
        $components = SalaryComponent::query()
            ->whereIn('id', array_keys($componentAmounts))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($components->count() !== count($componentAmounts)) {
            throw ValidationException::withMessages(['components' => ['One or more salary components are unknown or inactive.']]);
        }

        $basic = Money::ZERO;
        $gross = Money::ZERO;

        foreach ($componentAmounts as $componentId => $amount) {
            if (Money::isNegative($amount)) {
                throw ValidationException::withMessages(['components' => ['Salary component amounts cannot be negative.']]);
            }

            $gross = Money::add($gross, $amount);

            if ($components[$componentId]->type === SalaryComponentType::Basic) {
                $basic = Money::add($basic, $amount);
            }
        }

        if (Money::isZero($basic)) {
            throw ValidationException::withMessages(['components' => ['A salary must include a non-zero Basic Salary component.']]);
        }

        $current = $employee->currentSalary();

        if ($current !== null && $current->effective_from->greaterThanOrEqualTo($effectiveFrom)) {
            throw ValidationException::withMessages([
                'effective_from' => ["The effective date must be after the current salary's ({$current->effective_from->toDateString()})."],
            ]);
        }

        return DB::transaction(function () use ($employee, $effectiveFrom, $componentAmounts, $note, $actor, $current, $basic, $gross) {
            $current?->update(['ended_at' => Carbon::parse($effectiveFrom->toDateString())->subDay()->toDateString()]);

            $salary = EmployeeSalary::query()->create([
                'employee_id' => $employee->id,
                'effective_from' => $effectiveFrom->toDateString(),
                'basic_salary' => Money::round($basic),
                'gross_monthly' => Money::round($gross),
                'note' => $note,
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($componentAmounts as $componentId => $amount) {
                $salary->components()->create([
                    'salary_component_id' => $componentId,
                    'amount' => Money::round($amount),
                ]);
            }

            return $salary->load('components.component');
        });
    }

    /**
     * §59 — the salary version in force on a given date: effective on or
     * before it, and not yet ended.
     */
    public function salaryOn(Employee $employee, CarbonInterface $date): ?EmployeeSalary
    {
        return EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->with('components.component')
            ->first();
    }

    /**
     * §65 — daily salary for a period, by the method snapshotted on the
     * period. The monthly figure is the gross (basic + allowances), since
     * §67's "1 day salary" for overtime is understood as a full day's pay.
     */
    public function dailySalary(EmployeeSalary $salary, PayrollPeriod $period): string
    {
        return Money::round(Money::div($salary->gross_monthly, (string) $this->divisorDays($period)));
    }

    public function divisorDays(PayrollPeriod $period): int
    {
        return match ($period->salary_day_calculation_method_used) {
            SalaryDayCalculationMethod::Fixed30Days => 30,
            SalaryDayCalculationMethod::CalendarDays => (int) $period->start_date->diffInDays($period->end_date) + 1,
            SalaryDayCalculationMethod::WorkingDays => $this->workingDays($period),
        };
    }

    private function workingDays(PayrollPeriod $period): int
    {
        $settings = OrganizationSettings::current();
        $cursor = Carbon::parse($period->start_date->toDateString());
        $end = Carbon::parse($period->end_date->toDateString());
        $count = 0;

        while ($cursor->lessThanOrEqualTo($end)) {
            if (! $settings->isWeekend($cursor)) {
                $count++;
            }
            $cursor->addDay();
        }

        return max($count, 1);
    }
}
