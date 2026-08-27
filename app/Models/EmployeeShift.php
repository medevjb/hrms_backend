<?php

namespace App\Models;

use Database\Factories\EmployeeShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One employee's assignment to one shift. ended_at null means current — a
 * shift change closes this row rather than deleting it, so history
 * survives, mirroring TeamMember (docs/PRD.md §14).
 *
 * @property int $id
 * @property int $employee_id
 * @property int $shift_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 */
#[Fillable(['employee_id', 'shift_id', 'started_at', 'ended_at'])]
class EmployeeShift extends Model
{
    /** @use HasFactory<EmployeeShiftFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
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
}
