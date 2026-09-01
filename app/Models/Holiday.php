<?php

namespace App\Models;

use App\Enums\HolidaySource;
use App\Enums\HolidayType;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property Carbon $date
 * @property HolidayType $type
 * @property string|null $description
 * @property string|null $office_location
 * @property bool $active
 * @property HolidaySource $source
 * @property string|null $external_uid
 * @property Carbon|null $synced_at
 */
#[Fillable(['title', 'date', 'type', 'description', 'office_location', 'active', 'source', 'external_uid', 'synced_at'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $attributes = [
        'active' => true,
        'source' => HolidaySource::Manual->value,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => HolidayType::class,
            'active' => 'boolean',
            'source' => HolidaySource::class,
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<HolidayReminder, $this>
     */
    public function reminder(): HasOne
    {
        return $this->hasOne(HolidayReminder::class);
    }

    /**
     * @return HasOne<HolidayNotice, $this>
     */
    public function notice(): HasOne
    {
        return $this->hasOne(HolidayNotice::class);
    }
}
