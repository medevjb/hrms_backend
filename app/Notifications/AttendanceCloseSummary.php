<?php

namespace App\Notifications;

use App\Support\AttendanceCloseSummary as Summary;
use Illuminate\Notifications\Notification;

/**
 * The nightly close job's one notification to HR (docs/PRD.md §137) — not
 * one per employee it marked absent/missing-checkout/half-day.
 */
class AttendanceCloseSummary extends Notification
{
    public function __construct(private readonly Summary $summary) {}

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
        return $this->summary->toArray();
    }
}
