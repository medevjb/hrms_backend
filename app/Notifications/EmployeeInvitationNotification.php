<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when HR invites a new hire (App\Services\EmployeeService::invite).
 * Accepting the invitation IS setting the first password (docs/PRD.md
 * §148 #2), so the link lands on the frontend's reset-password page with
 * the invitation token. Queued — a slow SMTP handshake must not block the
 * invite request, and a transient failure should retry.
 */
class EmployeeInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var CanResetPassword $notifiable */
        $email = $notifiable->getEmailForPasswordReset();

        // Mirrors AppServiceProvider::configurePasswordResetUrl — the
        // reset-password page lives in the Next.js app, not Laravel.
        /** @var list<string> $allowedOrigins */
        $allowedOrigins = config('cors.allowed_origins', []);
        $frontendUrl = rtrim($allowedOrigins[0] ?? 'http://localhost:3000', '/');
        $url = "{$frontendUrl}/reset-password?token={$this->token}&email=".urlencode($email);

        return (new MailMessage)
            ->subject('You\'ve been invited to '.config('app.name'))
            ->greeting('Welcome aboard!')
            ->line('Your HR team has set up an account for you in '.config('app.name').'.')
            ->line('Set your password to sign in and finish your profile.')
            ->action('Set your password', $url)
            ->line('This invitation link expires in 72 hours. If it lapses, ask your HR team to send a new one.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'employee.invited',
        ];
    }
}
