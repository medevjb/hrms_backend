<?php

namespace App\Models;

use App\Enums\LatePenaltyDeductionMode;
use App\Enums\LatePenaltyOutcome;
use Database\Factories\LatePenaltyRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §61 — one tier of the late-penalty policy. See the migration
 * for how versions and tiers combine.
 *
 * @property int $id
 * @property Carbon $effective_from
 * @property int $late_days_threshold
 * @property LatePenaltyOutcome $outcome
 * @property LatePenaltyDeductionMode|null $deduction_mode
 * @property string|null $deduction_value
 * @property int|null $created_by_user_id
 */
#[Fillable([
    'effective_from', 'late_days_threshold', 'outcome',
    'deduction_mode', 'deduction_value', 'created_by_user_id',
])]
class LatePenaltyRule extends Model
{
    /** @use HasFactory<LatePenaltyRuleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'outcome' => LatePenaltyOutcome::class,
            'deduction_mode' => LatePenaltyDeductionMode::class,
            'deduction_value' => 'decimal:4',
        ];
    }
}
