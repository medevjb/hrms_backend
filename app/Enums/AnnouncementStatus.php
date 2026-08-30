<?php

namespace App\Enums;

/**
 * docs/PRD.md §57 — a draft is editable and invisible to its audience;
 * publishing resolves the audience, writes the notifications, and stamps
 * published_at; EXPIRED is set by the daily sweep once expires_at passes
 * (PublishDueAnnouncementsCommand).
 */
enum AnnouncementStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Expired = 'EXPIRED';
}
