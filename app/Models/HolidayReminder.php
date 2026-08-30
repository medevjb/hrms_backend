<?php

namespace App\Models;

use App\Enums\HolidayReminderStatus;
use Database\Factories\HolidayReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §55 — the dedupe guard for the five-day scan: one row per
 * holiday, created the first day the holiday falls inside the window, so
 * the daily cron never fires twice for it.
 *
 * @property int $id
 * @property int $holiday_id
 * @property int $lead_days_used
 * @property Carbon $triggered_on
 * @property HolidayReminderStatus $status
 * @property Carbon|null $head_hr_notified_at
 * @property int|null $actioned_by_user_id
 * @property Carbon|null $actioned_at
 */
#[Fillable([
    'holiday_id', 'lead_days_used', 'triggered_on', 'status',
    'head_hr_notified_at', 'actioned_by_user_id', 'actioned_at',
])]
class HolidayReminder extends Model
{
    /** @use HasFactory<HolidayReminderFactory> */
    use HasFactory;

    protected $attributes = ['status' => HolidayReminderStatus::Pending->value];

    protected function casts(): array
    {
        return [
            'triggered_on' => 'date',
            'status' => HolidayReminderStatus::class,
            'head_hr_notified_at' => 'datetime',
            'actioned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Holiday, $this>
     */
    public function holiday(): BelongsTo
    {
        return $this->belongsTo(Holiday::class);
    }

    /**
     * @return HasOne<HolidayNotice, $this>
     */
    public function notice(): HasOne
    {
        return $this->hasOne(HolidayNotice::class);
    }
}
