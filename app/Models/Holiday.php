<?php

namespace App\Models;

use App\Enums\HolidayType;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property Carbon $date
 * @property HolidayType $type
 * @property string|null $description
 * @property string|null $office_location
 * @property bool $active
 */
#[Fillable(['title', 'date', 'type', 'description', 'office_location', 'active'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => HolidayType::class,
            'active' => 'boolean',
        ];
    }
}
