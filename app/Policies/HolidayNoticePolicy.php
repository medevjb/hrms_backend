<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\HolidayNotice;
use App\Models\User;

/**
 * docs/PRD.md §55 — approving a holiday notice is Head HR's call, gated on
 * `holiday.notice.approve`. Viewing the queue and downloading a published
 * notice PDF only needs `holiday.view`.
 */
class HolidayNoticePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::HolidayView);
    }

    public function view(User $user, HolidayNotice $notice): bool
    {
        return $user->hasPermission(PermissionName::HolidayView);
    }

    public function approve(User $user, HolidayNotice $notice): bool
    {
        return $user->hasPermission(PermissionName::HolidayNoticeApprove);
    }

    public function download(User $user, HolidayNotice $notice): bool
    {
        return $notice->file_path !== null && $user->hasPermission(PermissionName::HolidayView);
    }
}
