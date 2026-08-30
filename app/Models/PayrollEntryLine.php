<?php

namespace App\Models;

use App\Enums\PayrollLineCategory;
use App\Enums\PayrollLineType;
use Database\Factories\PayrollEntryLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/PRD.md §71 — one itemised earning or deduction on a payslip.
 * `amount` is always positive; `category` gives the sign.
 *
 * @property int $id
 * @property int $payroll_entry_id
 * @property PayrollLineCategory $category
 * @property PayrollLineType $type
 * @property string $label
 * @property string $amount
 * @property array<string, mixed>|null $computed_from
 * @property string|null $source_type
 * @property int|null $source_id
 * @property bool $is_manual
 */
#[Fillable([
    'payroll_entry_id', 'category', 'type', 'label', 'amount',
    'computed_from', 'source_type', 'source_id', 'is_manual',
])]
class PayrollEntryLine extends Model
{
    /** @use HasFactory<PayrollEntryLineFactory> */
    use HasFactory;

    protected $attributes = ['is_manual' => false];

    protected function casts(): array
    {
        return [
            'category' => PayrollLineCategory::class,
            'type' => PayrollLineType::class,
            'amount' => 'decimal:4',
            'computed_from' => 'array',
            'is_manual' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PayrollEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }
}
