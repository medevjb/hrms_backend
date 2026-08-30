<?php

namespace App\Services;

use App\Enums\OvertimeStatus;
use App\Enums\PayrollAcknowledgementStatus;
use App\Enums\PayrollArrearSourceType;
use App\Enums\PayrollDisputeResolution;
use App\Enums\PayrollDisputeStatus;
use App\Enums\PayrollEntryStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PermissionName;
use App\Models\OrganizationSettings;
use App\Models\OvertimeRecord;
use App\Models\PayrollArrear;
use App\Models\PayrollDispute;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollSettings;
use App\Models\Payslip;
use App\Models\User;
use App\Notifications\PayrollDisputeRaised;
use App\Notifications\PayrollDisputeResolved;
use App\Notifications\PayrollReleased;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * docs/PRD.md §69/§70/§72/§146/§147 — the payroll period state machine
 * from draft through review, employee confirmation, finalisation
 * (payslips + arrears + overtime marked processed), payment, and locking,
 * plus the dispute lifecycle.
 */
class PayrollWorkflowService
{
    public function __construct(private readonly ArrearService $arrears) {}

    public function moveToReview(PayrollPeriod $period): PayrollPeriod
    {
        abort_unless($period->status === PayrollPeriodStatus::Processing, 409, 'Only a processed period can move to review.');
        abort_if($period->entries()->doesntExist(), 422, 'Generate the draft before moving to review.');

        $period->update(['status' => PayrollPeriodStatus::Review]);

        return $period->fresh();
    }

    /**
     * §69/§70 — release every entry to its employee for confirmation.
     */
    public function release(PayrollPeriod $period): PayrollPeriod
    {
        abort_unless(
            in_array($period->status, [PayrollPeriodStatus::Review, PayrollPeriodStatus::Processing], true),
            409,
            'This period is not ready to release.',
        );

        DB::transaction(function () use ($period) {
            $period->entries()->update([
                'status' => PayrollEntryStatus::Released,
                'acknowledgement_status' => PayrollAcknowledgementStatus::Pending,
                'released_at' => Carbon::now(),
            ]);
            $period->update(['status' => PayrollPeriodStatus::EmployeeConfirmation]);
        });

        $period->entries()->with('employee.user')->get()->each(function (PayrollEntry $entry) {
            if ($entry->employee->user !== null) {
                Notification::send($entry->employee->user, new PayrollReleased($entry));
            }
        });

        return $period->fresh();
    }

    /**
     * §70 — the employee confirms their payslip. Never changes a number.
     */
    public function acknowledge(PayrollEntry $entry, User $actor): PayrollEntry
    {
        abort_unless($entry->status === PayrollEntryStatus::Released, 409, 'This payslip has not been released.');
        abort_if(
            $entry->acknowledgement_status === PayrollAcknowledgementStatus::Disputed,
            409,
            'This payslip has an open dispute.',
        );

        $entry->update([
            'acknowledgement_status' => PayrollAcknowledgementStatus::Acknowledged,
            'acknowledged_at' => Carbon::now(),
        ]);

        return $entry->fresh();
    }

    /**
     * §147 — the employee disputes their payslip. Blocks finalisation of
     * this one entry only.
     */
    public function raiseDispute(PayrollEntry $entry, User $actor, string $reason): PayrollDispute
    {
        abort_unless($entry->status === PayrollEntryStatus::Released, 409, 'This payslip has not been released.');
        abort_if($entry->hasOpenDispute(), 409, 'This payslip already has an open dispute.');

        $dispute = DB::transaction(function () use ($entry, $actor, $reason) {
            $entry->update(['acknowledgement_status' => PayrollAcknowledgementStatus::Disputed]);

            return $entry->disputes()->create([
                'raised_by_user_id' => $actor->id,
                'reason' => $reason,
            ]);
        });

        $reviewers = User::query()->get()->filter(
            fn (User $user) => $user->hasPermission(PermissionName::PayrollDisputeResolve),
        );

        if ($reviewers->isNotEmpty()) {
            Notification::send($reviewers, new PayrollDisputeRaised($dispute));
        }

        return $dispute;
    }

    /**
     * §147 — HR records a resolution. Upheld sends the entry back to
     * PENDING for re-acknowledgement (HR is expected to apply the §68
     * adjustment separately); rejected closes it with the explanation on
     * record. "A dispute resolved without an explanation is not resolved."
     */
    public function resolveDispute(
        PayrollDispute $dispute,
        User $actor,
        PayrollDisputeResolution $resolution,
        string $note,
    ): PayrollDispute {
        abort_unless($dispute->status === PayrollDisputeStatus::Open, 409, 'This dispute is already resolved.');

        DB::transaction(function () use ($dispute, $actor, $resolution, $note) {
            $dispute->update([
                'status' => PayrollDisputeStatus::Resolved,
                'resolution' => $resolution,
                'resolution_note' => $note,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => Carbon::now(),
            ]);

            $dispute->entry->update([
                'acknowledgement_status' => $resolution === PayrollDisputeResolution::Upheld
                    ? PayrollAcknowledgementStatus::Pending
                    : PayrollAcknowledgementStatus::Resolved,
            ]);
        });

        if ($dispute->entry->employee->user !== null) {
            Notification::send($dispute->entry->employee->user, new PayrollDisputeResolved($dispute->fresh()));
        }

        return $dispute->fresh();
    }

    /**
     * §69/§71/§72 — finalise: auto-acknowledge entries past the §147
     * dispute window, refuse if any dispute is still open, then per entry
     * render the payslip PDF, mark its approved overtime PAYROLL_PROCESSED,
     * and mark claimed arrears APPLIED.
     */
    public function finalize(PayrollPeriod $period, User $actor): PayrollPeriod
    {
        abort_unless(
            in_array($period->status, [PayrollPeriodStatus::EmployeeConfirmation, PayrollPeriodStatus::Review], true),
            409,
            'This period is not ready to finalise.',
        );

        $this->autoAcknowledgeExpired($period);

        $blocked = $period->entries()
            ->where('acknowledgement_status', PayrollAcknowledgementStatus::Disputed)
            ->count();

        abort_if($blocked > 0, 409, "{$blocked} payslip(s) have an unresolved dispute — resolve or defer them first.");

        DB::transaction(function () use ($period) {
            foreach ($period->entries()->with(['employee', 'lines'])->get() as $entry) {
                $this->generatePayslip($entry);
                $entry->update(['status' => PayrollEntryStatus::Finalized, 'finalized_at' => Carbon::now()]);
            }

            OvertimeRecord::query()
                ->where('status', OvertimeStatus::Approved)
                ->whereBetween('work_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
                ->update(['status' => OvertimeStatus::PayrollProcessed, 'payroll_processed_at' => Carbon::now()]);

            $this->arrears->markApplied($period);

            $period->update(['status' => PayrollPeriodStatus::Finalized, 'finalized_at' => Carbon::now()]);
        });

        return $period->fresh();
    }

    public function markPaid(PayrollPeriod $period): PayrollPeriod
    {
        abort_unless($period->status === PayrollPeriodStatus::Finalized, 409, 'Only a finalised period can be marked paid.');
        $period->update(['status' => PayrollPeriodStatus::Paid]);

        return $period->fresh();
    }

    public function lock(PayrollPeriod $period): PayrollPeriod
    {
        abort_unless($period->status === PayrollPeriodStatus::Paid, 409, 'Only a paid period can be locked.');
        $period->update(['status' => PayrollPeriodStatus::Locked]);

        return $period->fresh();
    }

    /**
     * §147 — after the dispute window closes, PENDING entries are treated
     * as acknowledged and logged as auto-acknowledged, so one unresponsive
     * employee doesn't stall payroll indefinitely.
     */
    public function autoAcknowledgeExpired(PayrollPeriod $period): int
    {
        $window = PayrollSettings::current()->dispute_window_days;

        return $period->entries()
            ->where('acknowledgement_status', PayrollAcknowledgementStatus::Pending)
            ->whereNotNull('released_at')
            ->where('released_at', '<=', Carbon::now()->subDays($window))
            ->update([
                'acknowledgement_status' => PayrollAcknowledgementStatus::AutoAcknowledged,
                'acknowledged_at' => Carbon::now(),
            ]);
    }

    private function generatePayslip(PayrollEntry $entry): Payslip
    {
        $entry->loadMissing(['employee', 'lines', 'period']);
        $settings = OrganizationSettings::current();

        $reference = sprintf(
            'PS-%s-%04d',
            $entry->period->end_date->format('Y-m'),
            $entry->id,
        );

        $pdf = Pdf::loadView('pdf.payslip', [
            'entry' => $entry,
            'employee' => $entry->employee,
            'period' => $entry->period,
            'settings' => $settings,
            'reference' => $reference,
        ]);

        $path = "payslips/{$reference}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        return Payslip::query()->updateOrCreate(
            ['payroll_entry_id' => $entry->id],
            [
                'payroll_period_id' => $entry->payroll_period_id,
                'employee_id' => $entry->employee_id,
                'reference' => $reference,
                'gross_earnings' => $entry->gross_earnings,
                'total_deductions' => $entry->total_deductions,
                'net_salary' => $entry->net_salary,
                'file_path' => $path,
                'generated_at' => Carbon::now(),
            ],
        );
    }

    /**
     * §146 — defer a still-disputed entry to the next period as an arrear
     * so the rest of the period can finalise.
     */
    public function deferDisputedEntry(PayrollEntry $entry, User $actor): PayrollArrear
    {
        abort_unless($entry->hasOpenDispute(), 409, 'This entry has no open dispute to defer.');

        return DB::transaction(function () use ($entry, $actor) {
            $entry->update([
                'status' => PayrollEntryStatus::Finalized,
                'acknowledgement_status' => PayrollAcknowledgementStatus::Resolved,
                'finalized_at' => Carbon::now(),
            ]);

            return PayrollArrear::query()->create([
                'employee_id' => $entry->employee_id,
                'source_type' => PayrollArrearSourceType::Correction,
                'source_id' => $entry->id,
                'original_period_id' => $entry->payroll_period_id,
                'amount' => $entry->net_salary,
                'reason' => "Disputed {$entry->period->label} payslip deferred pending resolution.",
                'created_by_user_id' => $actor->id,
            ]);
        });
    }
}
