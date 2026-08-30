<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;

/**
 * docs/PRD.md §57/§80 — an announcement went live. Always IN_APP; EMAIL is
 * layered on for the types an employee "cannot afford to miss"
 * (AnnouncementType::warrantsEmail() — HOLIDAY, PAYROLL, EMERGENCY,
 * POLICY, HR_NOTICE) or any announcement flagged for acknowledgement.
 * Queued because publishing an ALL-audience post is a bulk send (§81).
 */
class AnnouncementPublished extends Notification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly Announcement $announcement) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->announcement->type->warrantsEmail() || $this->announcement->acknowledgement_required) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("{$this->announcement->title}")
            ->line($this->announcement->content);

        if ($this->announcement->acknowledgement_required) {
            $mail->line('Please open the announcement in the HR portal and acknowledge that you have read it.');
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'announcement.published',
            'announcement_id' => $this->announcement->id,
            'type' => $this->announcement->type->value,
            'title' => $this->announcement->title,
            'acknowledgement_required' => $this->announcement->acknowledgement_required,
        ];
    }
}
