<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int|null $operation_manager_id
 * @property string|null $description
 * @property bool $active
 */
#[Fillable(['name', 'operation_manager_id', 'description', 'active'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    // Mirrors the migration's column default so a create() response
    // reflects it immediately, without a round-trip refresh.
    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function operationManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operation_manager_id');
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}
