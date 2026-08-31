<?php

namespace App\Models;

use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $start_time
 * @property string $end_time
 * @property int $expected_work_minutes
 * @property int $break_minutes
 * @property string|null $break_start
 * @property string|null $break_end
 * @property int|null $late_grace_minutes
 * @property bool $active
 */
#[Fillable(['name', 'start_time', 'end_time', 'expected_work_minutes', 'break_minutes', 'break_start', 'break_end', 'late_grace_minutes', 'active'])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Keep break_minutes in step with the window whenever one is set —
        // attendance math reads break_minutes, the schedule UI reads the
        // window, and the two should never disagree.
        static::saving(function (Shift $shift) {
            if ($shift->break_start !== null && $shift->break_end !== null) {
                $shift->break_minutes = (int) Carbon::parse($shift->break_start)
                    ->diffInMinutes(Carbon::parse($shift->break_end), absolute: true);
            }
        });
    }

    /**
     * True when this shift crosses midnight (e.g. 20:00-05:00) — the shift
     * "belongs" to its start date even though most of the work happens the
     * next calendar day (docs/PRD.md §136).
     */
    public function isOvernight(): bool
    {
        return $this->end_time <= $this->start_time;
    }

    /**
     * null = use organization_settings.late_grace_minutes.
     */
    public function resolveGraceMinutes(OrganizationSettings $settings): int
    {
        return $this->late_grace_minutes ?? $settings->late_grace_minutes;
    }
}
