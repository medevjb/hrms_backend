<?php

namespace App\Notifications;

use App\Models\PayrollDispute;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;

/**
 * docs/PRD.md §147 — the employee is told the outcome and the explanation.
 * IN_APP + EMAIL.
 */
class PayrollDisputeResolved extends Notification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly PayrollDispute $dispute) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your payroll dispute has been resolved')
            ->line("Outcome: {$this->dispute->resolution?->value}")
            ->line($this->dispute->resolution_note ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'payroll.dispute.resolved',
            'payroll_dispute_id' => $this->dispute->id,
            'resolution' => $this->dispute->resolution?->value,
            'resolution_note' => $this->dispute->resolution_note,
        ];
    }
}
