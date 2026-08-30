<?php

namespace App\Console\Commands;

use App\Services\AnnouncementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §57 — publishes drafts whose scheduled publish_at has
 * arrived and expires published announcements past their expires_at.
 * Scheduled hourly (routes/console.php); a no-op when nothing is due.
 */
#[Signature('announcements:publish-due')]
#[Description('Publish scheduled announcements and expire ones past their expiry date')]
class PublishDueAnnouncementsCommand extends Command
{
    public function handle(AnnouncementService $announcements): int
    {
        $result = $announcements->runDueSweep(Carbon::now());

        $this->components->info(
            "Announcements: {$result['published']} published, {$result['expired']} expired.",
        );

        return self::SUCCESS;
    }
}
