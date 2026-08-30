<?php

namespace App\Models;

use App\Enums\AnnouncementAudienceType;
use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §57 — a company announcement. Draft until published (by hand
 * or by the daily sweep once publish_at passes); publishing resolves the
 * audience and writes one notification per targeted employee (§80, via the
 * database queue for bulk sends — §81).
 *
 * @property int $id
 * @property AnnouncementType $type
 * @property string $title
 * @property string $content
 * @property AnnouncementAudienceType $audience_type
 * @property AnnouncementStatus $status
 * @property string|null $attachment_path
 * @property bool $acknowledgement_required
 * @property Carbon|null $publish_at
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 * @property int $created_by_user_id
 * @property int|null $holiday_notice_id
 */
#[Fillable([
    'type', 'title', 'content', 'audience_type', 'status', 'attachment_path',
    'acknowledgement_required', 'publish_at', 'published_at', 'expires_at',
    'created_by_user_id', 'holiday_notice_id',
])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    protected $attributes = ['status' => AnnouncementStatus::Draft->value];

    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'audience_type' => AnnouncementAudienceType::class,
            'status' => AnnouncementStatus::class,
            'acknowledgement_required' => 'boolean',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AnnouncementTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    /**
     * @return HasMany<AnnouncementRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isPublished(): bool
    {
        return $this->status === AnnouncementStatus::Published;
    }
}
