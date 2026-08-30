<?php

namespace App\Support;

use App\Enums\ReportType;

/**
 * A built report: its column list and its rows (each row keyed by column
 * key). ReportService produces one; ReportController renders it as a JSON
 * preview or streams it as CSV (docs/PRD.md §99).
 */
readonly class Report
{
    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  array<int, array<string, string|int|float|null>>  $rows
     */
    public function __construct(
        public ReportType $type,
        public array $columns,
        public array $rows,
    ) {}

    /**
     * @return array<int, string>
     */
    public function headerRow(): array
    {
        return array_map(fn (array $column) => $column['label'], $this->columns);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function bodyRows(): array
    {
        return array_map(
            fn (array $row) => array_map(
                fn (array $column) => (string) ($row[$column['key']] ?? ''),
                $this->columns,
            ),
            $this->rows,
        );
    }
}
