<?php

namespace App\Models;

use Database\Factories\ShiftOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One employee's shift for one specific day (docs/PRD.md §23) — never
 * touches their regular assignment in employee_shifts.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $shift_id
 * @property Carbon $work_date
 * @property string $reason
 * @property int|null $changed_by
 */
#[Fillable(['employee_id', 'shift_id', 'work_date', 'reason', 'changed_by'])]
class ShiftOverride extends Model
{
    /** @use HasFactory<ShiftOverrideFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['work_date' => 'date'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
