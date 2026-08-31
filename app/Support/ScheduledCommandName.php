<?php

namespace App\Support;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;

/**
 * A stable, human-readable key for a scheduled event — `attendance:close`
 * rather than the full `'/usr/bin/php8.4' 'artisan' attendance:close`. Used
 * both to tag run records and to match them back to the live schedule on the
 * Schedule page.
 */
class ScheduledCommandName
{
    public static function for(Event $event): string
    {
        if ($event instanceof CallbackEvent) {
            return $event->getSummaryForDisplay();
        }

        $command = Event::normalizeCommand($event->command ?? '');

        // Strip the `php artisan ` prefix the normalizer leaves behind.
        $command = preg_replace('/^php\s+artisan\s+/', '', trim($command));

        return $command !== '' ? $command : $event->getSummaryForDisplay();
    }
}
