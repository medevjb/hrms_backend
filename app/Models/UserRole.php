<?php

namespace App\Models;

use App\Enums\Scope;
use Database\Factories\UserRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single grant: this user holds this role, at this scope, optionally
 * targeted at scope_id (docs/PRD.md §10). A user may hold the same role at
 * different scopes — e.g. Team Leader of Team A, not of Team B — so scope
 * lives on the grant, never on the role itself.
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property Scope $scope
 * @property int|null $scope_id
 */
#[Fillable(['user_id', 'role_id', 'scope', 'scope_id'])]
class UserRole extends Model
{
    /** @use HasFactory<UserRoleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scope' => Scope::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
