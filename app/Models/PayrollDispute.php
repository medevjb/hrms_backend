<?php

namespace App\Models;

use App\Enums\PayrollDisputeResolution;
use App\Enums\PayrollDisputeStatus;
use Database\Factories\PayrollDisputeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §147 — an employee's dispute of a released payroll entry.
 *
 * @property int $id
 * @property int $payroll_entry_id
 * @property int $raised_by_user_id
 * @property string $reason
 * @property PayrollDisputeStatus $status
 * @property PayrollDisputeResolution|null $resolution
 * @property string|null $resolution_note
 * @property int|null $resolved_by_user_id
 * @property Carbon|null $resolved_at
 */
#[Fillable([
    'payroll_entry_id', 'raised_by_user_id', 'reason', 'status', 'resolution',
    'resolution_note', 'resolved_by_user_id', 'resolved_at',
])]
class PayrollDispute extends Model
{
    /** @use HasFactory<PayrollDisputeFactory> */
    use HasFactory;

    protected $attributes = ['status' => PayrollDisputeStatus::Open->value];

    protected function casts(): array
    {
        return [
            'status' => PayrollDisputeStatus::class,
            'resolution' => PayrollDisputeResolution::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user_id');
    }
}
