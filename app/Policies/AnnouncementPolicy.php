<?php

namespace App\Policies;

use App\Enums\AnnouncementStatus;
use App\Enums\PermissionName;
use App\Models\Announcement;
use App\Models\User;

/**
 * docs/PRD.md §57 — anyone with `announcement.view` sees the ones aimed at
 * them; `announcement.create` drafts and edits; `announcement.publish`
 * releases them. A draft is only visible to someone who can create or
 * publish (i.e. the HR side), never to its future audience.
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::AnnouncementView);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        if (! $user->hasPermission(PermissionName::AnnouncementView)) {
            return false;
        }

        if ($this->isAuthor($user)) {
            return true;
        }

        return $announcement->status === AnnouncementStatus::Published;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::AnnouncementCreate);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $announcement->status === AnnouncementStatus::Draft
            && $user->hasPermission(PermissionName::AnnouncementCreate);
    }

    public function publish(User $user, Announcement $announcement): bool
    {
        return $user->hasPermission(PermissionName::AnnouncementPublish);
    }

    /**
     * Only a draft can be deleted — a published announcement is a record of
     * something people were shown, so it stays.
     */
    public function delete(User $user, Announcement $announcement): bool
    {
        return $announcement->status === AnnouncementStatus::Draft
            && $user->hasPermission(PermissionName::AnnouncementCreate);
    }

    public function read(User $user, Announcement $announcement): bool
    {
        return $user->employee !== null
            && $announcement->status === AnnouncementStatus::Published
            && $user->hasPermission(PermissionName::AnnouncementView);
    }

    private function isAuthor(User $user): bool
    {
        return $user->hasPermission(PermissionName::AnnouncementCreate)
            || $user->hasPermission(PermissionName::AnnouncementPublish);
    }
}
