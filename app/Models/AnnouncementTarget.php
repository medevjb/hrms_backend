<?php

namespace App\Models;

use App\Enums\AnnouncementTargetType;
use Database\Factories\AnnouncementTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/PRD.md §57 — one department / team / role / employee an announcement
 * targets. Resolved into an employee set at publish time by
 * AnnouncementService.
 *
 * @property int $id
 * @property int $announcement_id
 * @property AnnouncementTargetType $target_type
 * @property int $target_id
 */
#[Fillable(['announcement_id', 'target_type', 'target_id'])]
class AnnouncementTarget extends Model
{
    /** @use HasFactory<AnnouncementTargetFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['target_type' => AnnouncementTargetType::class];
    }

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
