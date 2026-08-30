<?php

namespace App\Enums;

/**
 * docs/PRD.md §57 — the fixed V1 announcement type set. EMERGENCY and
 * POLICY are the two that carry an acknowledgement requirement by default
 * (§57: "Who has seen the policy" is the reason announcement_reads exists).
 */
enum AnnouncementType: string
{
    case General = 'GENERAL';
    case HrNotice = 'HR_NOTICE';
    case Holiday = 'HOLIDAY';
    case Payroll = 'PAYROLL';
    case Policy = 'POLICY';
    case Emergency = 'EMERGENCY';
    case Team = 'TEAM';

    /**
     * §80 — every notification is IN_APP; EMAIL is layered on for the ones
     * "the employee cannot afford to miss". A holiday closure, a payroll
     * notice, an emergency, and an explicit policy all qualify; a general
     * or team chat-style post does not.
     */
    public function warrantsEmail(): bool
    {
        return match ($this) {
            self::Holiday, self::Payroll, self::Emergency, self::Policy, self::HrNotice => true,
            self::General, self::Team => false,
        };
    }

    public function defaultsToAcknowledgement(): bool
    {
        return match ($this) {
            self::Emergency, self::Policy => true,
            default => false,
        };
    }
}
