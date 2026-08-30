<?php

namespace App\Http\Requests\Api\V1\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/PRD.md §99 — the filters shared by every report. All optional; a
 * date report with no range defaults to the current month.
 */
class BuildReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy check happens in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter.date_from' => ['sometimes', 'nullable', 'date'],
            'filter.date_to' => ['sometimes', 'nullable', 'date'],
            'filter.department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')],
            'filter.team_id' => ['sometimes', 'nullable', 'integer', Rule::exists('teams', 'id')],
            'filter.employee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')],
            'filter.payroll_period_id' => ['sometimes', 'nullable', 'integer', Rule::exists('payroll_periods', 'id')],
        ];
    }

    /**
     * @return array{date_from: string|null, date_to: string|null, department_id: int|null, team_id: int|null, employee_id: int|null, payroll_period_id: int|null}
     */
    public function filters(): array
    {
        return [
            'date_from' => $this->input('filter.date_from'),
            'date_to' => $this->input('filter.date_to'),
            'department_id' => $this->integer('filter.department_id') ?: null,
            'team_id' => $this->integer('filter.team_id') ?: null,
            'employee_id' => $this->integer('filter.employee_id') ?: null,
            'payroll_period_id' => $this->integer('filter.payroll_period_id') ?: null,
        ];
    }
}
