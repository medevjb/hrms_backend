<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Database\Factories\EmployeeStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property EmployeeStatus|null $from_status
 * @property EmployeeStatus $to_status
 * @property string|null $reason
 * @property int|null $changed_by
 */
#[Fillable(['employee_id', 'from_status', 'to_status', 'reason', 'changed_by'])]
class EmployeeStatusHistory extends Model
{
    /** @use HasFactory<EmployeeStatusHistoryFactory> */
    use HasFactory;

    // "history" doesn't pluralize the way Eloquent's convention expects.
    protected $table = 'employee_status_history';

    protected function casts(): array
    {
        return [
            'from_status' => EmployeeStatus::class,
            'to_status' => EmployeeStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
