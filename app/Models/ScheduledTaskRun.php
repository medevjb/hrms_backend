<?php

namespace App\Models;

use App\Enums\ScheduledTaskStatus;
use Database\Factories\ScheduledTaskRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §79 — one execution of a scheduled console command.
 *
 * @property int $id
 * @property string $command
 * @property ScheduledTaskStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property int|null $exit_code
 * @property string|null $output
 * @property Carbon|null $created_at
 */
#[Fillable([
    'command', 'status', 'started_at', 'finished_at', 'duration_ms', 'exit_code', 'output',
])]
class ScheduledTaskRun extends Model
{
    /** @use HasFactory<ScheduledTaskRunFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'status' => ScheduledTaskStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<ScheduledTaskRun>  $query
     * @return Builder<ScheduledTaskRun>
     */
    public function scopeForCommand(Builder $query, string $command): Builder
    {
        return $query->where('command', $command);
    }
}
