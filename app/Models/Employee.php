<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * department/team/Team Leader/Operation Manager (docs/PRD.md §12) are
 * deliberately not columns here — they're derived through currentTeam(),
 * so a transfer (§14) can't leave two contradictory sources of truth.
 *
 * @property int $id
 * @property int $user_id
 * @property string $employee_code
 * @property string $first_name
 * @property string $last_name
 * @property string|null $profile_image_path
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property Carbon $joining_date
 * @property string $designation
 * @property EmploymentType $employment_type
 * @property EmployeeStatus $status
 * @property Carbon|null $confirmation_date
 * @property string|null $office_location
 * @property string|null $timezone
 * @property bool $overtime_eligible
 */
#[Fillable([
    'user_id', 'employee_code', 'first_name', 'last_name', 'profile_image_path',
    'phone', 'address', 'emergency_contact_name', 'emergency_contact_phone',
    'joining_date', 'designation', 'employment_type', 'status', 'confirmation_date',
    'office_location', 'timezone', 'overtime_eligible',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'confirmation_date' => 'date',
            'employment_type' => EmploymentType::class,
            'status' => EmployeeStatus::class,
            'overtime_eligible' => 'boolean',
        ];
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TeamMember, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * @return HasOne<TeamMember, $this>
     */
    public function currentTeamMembership(): HasOne
    {
        return $this->hasOne(TeamMember::class)->whereNull('ended_at');
    }

    public function currentTeam(): ?Team
    {
        return $this->currentTeamMembership?->team;
    }

    public function teamLeader(): ?self
    {
        return $this->currentTeam()?->teamLeader;
    }

    public function operationManager(): ?self
    {
        return $this->currentTeam()?->department?->operationManager;
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'team_leader_id');
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function managedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'operation_manager_id');
    }

    /**
     * @return HasMany<EmployeeStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(EmployeeStatusHistory::class);
    }

    /**
     * @return HasMany<EmployeeShift, $this>
     */
    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }

    /**
     * @return HasOne<EmployeeShift, $this>
     */
    public function currentShiftAssignment(): HasOne
    {
        return $this->hasOne(EmployeeShift::class)->whereNull('ended_at');
    }

    public function currentShift(): ?Shift
    {
        return $this->currentShiftAssignment?->shift;
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<LeaveBalance, $this>
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * @return HasMany<OvertimeRecord, $this>
     */
    public function overtimeRecords(): HasMany
    {
        return $this->hasMany(OvertimeRecord::class);
    }

    /**
     * @return HasMany<EmployeeSalary, $this>
     */
    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function currentSalary(): ?EmployeeSalary
    {
        return $this->salaries()->whereNull('ended_at')->latest('effective_from')->first();
    }

    /**
     * @return HasMany<PayrollEntry, $this>
     */
    public function payrollEntries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    /**
     * @return HasMany<PayrollArrear, $this>
     */
    public function payrollArrears(): HasMany
    {
        return $this->hasMany(PayrollArrear::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
