<?php

namespace App\Notifications;

use App\Models\PayrollDispute;
use Illuminate\Notifications\Notification;

/**
 * docs/PRD.md §147 — an employee disputed their payslip; whoever holds
 * payroll.dispute.resolve needs to investigate.
 */
class PayrollDisputeRaised extends Notification
{
    public function __construct(private readonly PayrollDispute $dispute) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'payroll.dispute.raised',
            'payroll_dispute_id' => $this->dispute->id,
            'payroll_entry_id' => $this->dispute->payroll_entry_id,
            'reason' => $this->dispute->reason,
        ];
    }
}
