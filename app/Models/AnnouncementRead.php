<?php

namespace App\Models;

use Database\Factories\AnnouncementReadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §57 — an employee's read (and, for EMERGENCY/POLICY, explicit
 * acknowledgement) of an announcement.
 *
 * @property int $id
 * @property int $announcement_id
 * @property int $employee_id
 * @property bool $acknowledged
 * @property Carbon $read_at
 */
#[Fillable(['announcement_id', 'employee_id', 'acknowledged', 'read_at'])]
class AnnouncementRead extends Model
{
    /** @use HasFactory<AnnouncementReadFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'acknowledged' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
