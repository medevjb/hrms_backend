<?php

namespace App\Console\Commands;

use App\Services\BangladeshHolidayImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Syncs the standard Bangladesh national public holidays from Google's
 * public "Holidays in Bangladesh" calendar. Scheduled weekly
 * (routes/console.php) and also runnable on demand from the holiday
 * calendar's "Sync now" button. Idempotent — see BangladeshHolidayImporter.
 */
#[Signature('holidays:import-bd')]
#[Description('Import Bangladesh public holidays from the Google calendar feed')]
class ImportBangladeshHolidaysCommand extends Command
{
    public function handle(BangladeshHolidayImporter $importer): int
    {
        $result = $importer->import();

        $this->components->info(sprintf(
            'Bangladesh holidays synced: %d created, %d updated, %d skipped.',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
