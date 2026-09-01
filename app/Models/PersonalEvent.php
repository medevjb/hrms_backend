<?php

namespace App\Models;

use Database\Factories\PersonalEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A private note an employee drops on their own calendar — a single day
 * or a span. Purely personal: it has no bearing on attendance, work-day
 * math, leave, or anyone else's view (only the owning employee ever sees
 * it).
 *
 * @property int $id
 * @property int $employee_id
 * @property string $title
 * @property string|null $description
 * @property Carbon $start_date
 * @property Carbon $end_date
 */
#[Fillable(['employee_id', 'title', 'description', 'start_date', 'end_date'])]
class PersonalEvent extends Model
{
    /** @use HasFactory<PersonalEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
