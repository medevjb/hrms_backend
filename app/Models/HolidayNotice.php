<?php

namespace App\Models;

use App\Enums\HolidayNoticeStatus;
use Database\Factories\HolidayNoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §56 — the notice document. Drafted PENDING_APPROVAL by the
 * scan with an auto-composed message and return date; Head HR edits and
 * signs it via §91 /holiday-notices/{id}/approve, which renders the PDF
 * (§56 fields), stores it privately (§82), and publishes the linked
 * HOLIDAY announcement.
 *
 * @property int $id
 * @property int $holiday_id
 * @property int|null $holiday_reminder_id
 * @property string $reference
 * @property HolidayNoticeStatus $status
 * @property string $title
 * @property string $message
 * @property string|null $closure_note
 * @property Carbon|null $return_date
 * @property string|null $signatory_name
 * @property int|null $signatory_user_id
 * @property Carbon|null $generated_at
 * @property string|null $file_path
 * @property int|null $announcement_id
 */
#[Fillable([
    'holiday_id', 'holiday_reminder_id', 'reference', 'status', 'title', 'message',
    'closure_note', 'return_date', 'signatory_name', 'signatory_user_id',
    'generated_at', 'file_path', 'announcement_id',
])]
class HolidayNotice extends Model
{
    /** @use HasFactory<HolidayNoticeFactory> */
    use HasFactory;

    protected $attributes = ['status' => HolidayNoticeStatus::PendingApproval->value];

    protected function casts(): array
    {
        return [
            'status' => HolidayNoticeStatus::class,
            'return_date' => 'date',
            'generated_at' => 'datetime',
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
     * @return BelongsTo<HolidayReminder, $this>
     */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(HolidayReminder::class, 'holiday_reminder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signatory(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signatory_user_id');
    }

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function isPublished(): bool
    {
        return $this->status === HolidayNoticeStatus::Published;
    }
}
