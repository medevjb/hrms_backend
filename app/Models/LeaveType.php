<?php

namespace App\Models;

use App\Enums\LeaveAccrualMode;
use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $annual_allocation_days
 * @property bool $is_paid
 * @property bool $supports_half_day
 * @property bool $carry_forward_enabled
 * @property string|null $carry_forward_cap_days
 * @property bool $requires_document
 * @property int|null $max_consecutive_days
 * @property int|null $min_employment_days
 * @property LeaveAccrualMode $accrual_mode
 * @property bool $is_active
 */
#[Fillable([
    'name', 'code', 'annual_allocation_days', 'is_paid', 'supports_half_day',
    'carry_forward_enabled', 'carry_forward_cap_days', 'requires_document',
    'max_consecutive_days', 'min_employment_days', 'accrual_mode', 'is_active',
])]
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    protected $attributes = [
        'is_paid' => true,
        'supports_half_day' => true,
        'carry_forward_enabled' => false,
        'requires_document' => false,
        'accrual_mode' => 'UPFRONT',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'annual_allocation_days' => 'decimal:1',
            'carry_forward_cap_days' => 'decimal:1',
            'is_paid' => 'boolean',
            'supports_half_day' => 'boolean',
            'carry_forward_enabled' => 'boolean',
            'requires_document' => 'boolean',
            'accrual_mode' => LeaveAccrualMode::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<LeaveBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
