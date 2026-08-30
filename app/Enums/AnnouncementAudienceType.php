<?php

namespace App\Enums;

/**
 * docs/PRD.md §57 — how an announcement's audience is expressed. ALL needs
 * no announcement_targets rows; the other four each resolve through
 * targets of the matching AnnouncementTargetType (SELECTED → EMPLOYEE).
 */
enum AnnouncementAudienceType: string
{
    case All = 'ALL';
    case Department = 'DEPARTMENT';
    case Team = 'TEAM';
    case Role = 'ROLE';
    case Selected = 'SELECTED';

    public function targetType(): ?AnnouncementTargetType
    {
        return match ($this) {
            self::All => null,
            self::Department => AnnouncementTargetType::Department,
            self::Team => AnnouncementTargetType::Team,
            self::Role => AnnouncementTargetType::Role,
            self::Selected => AnnouncementTargetType::Employee,
        };
    }
}
