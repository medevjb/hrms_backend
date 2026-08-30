<?php

namespace App\Notifications;

use App\Models\PayrollEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;

/**
 * docs/PRD.md §70/§80 — an employee's payslip has been released for
 * confirmation. IN_APP + EMAIL (payroll is on §80's "cannot afford to
 * miss" list); queued because a whole period releases at once (§81).
 */
class PayrollReleased extends Notification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly PayrollEntry $entry) {}

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
            ->subject("Your {$this->entry->period->label} payslip is ready")
            ->line("Net salary: {$this->entry->net_salary}")
            ->line('Please review it in the HR portal and confirm, or report an issue.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'payroll.released',
            'payroll_entry_id' => $this->entry->id,
            'period' => $this->entry->period->label,
            'net_salary' => (string) $this->entry->net_salary,
        ];
    }
}
