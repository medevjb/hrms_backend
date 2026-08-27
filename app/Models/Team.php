<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $department_id
 * @property string $name
 * @property int|null $team_leader_id
 * @property bool $active
 */
#[Fillable(['department_id', 'name', 'team_leader_id', 'active'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    // Mirrors the migration's column default so a create() response
    // reflects it immediately, without a round-trip refresh.
    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'team_leader_id');
    }

    /**
     * @return HasMany<TeamMember, $this>
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * @return HasMany<TeamMember, $this>
     */
    public function currentTeamMembers(): HasMany
    {
        return $this->teamMembers()->whereNull('ended_at');
    }
}
