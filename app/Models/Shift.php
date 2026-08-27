<?php

namespace App\Models;

use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $start_time
 * @property string $end_time
 * @property int $expected_work_minutes
 * @property int $break_minutes
 * @property int|null $late_grace_minutes
 * @property bool $active
 */
#[Fillable(['name', 'start_time', 'end_time', 'expected_work_minutes', 'break_minutes', 'late_grace_minutes', 'active'])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
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
