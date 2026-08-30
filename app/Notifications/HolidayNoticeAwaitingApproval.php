<?php

namespace App\Notifications;

use App\Models\HolidayNotice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * docs/PRD.md §55 — the five-day scan drafted a holiday notice; Head HR
 * needs to review and sign it before anything reaches employees. IN_APP +
 * EMAIL, since a missed closure notice is exactly the kind of event §80
 * layers email on for.
 */
class HolidayNoticeAwaitingApproval extends Notification
{
    public function __construct(private readonly HolidayNotice $notice) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $holiday = $this->notice->holiday;

        return (new MailMessage)
            ->subject("Holiday notice awaiting your approval — {$holiday->title}")
            ->line("A holiday notice has been drafted for {$holiday->title} on {$holiday->date->toFormattedDateString()}.")
            ->line('It will not be published to employees until you approve it.')
            ->line("Reference: {$this->notice->reference}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'holiday_notice.awaiting_approval',
            'holiday_notice_id' => $this->notice->id,
            'reference' => $this->notice->reference,
            'holiday_id' => $this->notice->holiday_id,
            'holiday_title' => $this->notice->holiday->title,
            'holiday_date' => $this->notice->holiday->date->toDateString(),
        ];
    }
}
