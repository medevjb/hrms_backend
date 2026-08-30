<?php

namespace App\Enums;

/**
 * docs/PRD.md §57 — the kind of thing an announcement_targets row points
 * at. `target_id` is a departments / teams / roles / employees primary
 * key depending on this value.
 */
enum AnnouncementTargetType: string
{
    case Department = 'DEPARTMENT';
    case Team = 'TEAM';
    case Role = 'ROLE';
    case Employee = 'EMPLOYEE';
}
