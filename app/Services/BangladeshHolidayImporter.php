<?php

namespace App\Services;

use App\Enums\HolidaySource;
use App\Enums\HolidayType;
use App\Models\Holiday;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pulls the standard Bangladesh national public holidays from Google's
 * public "Holidays in Bangladesh" iCal feed and upserts them as Holiday
 * rows tagged source = GOOGLE_BD.
 *
 * Rules that keep it safe to re-run (weekly, plus the manual "Sync now"
 * button):
 *   - Matches its own rows by the calendar event's Google UID, so a
 *     shifted religious date (Eid, Puja) updates in place.
 *   - Never creates a second row on a date that already has a holiday,
 *     so a hand-added entry is left alone.
 *   - Only ever writes rows it owns; a MANUAL row is never modified.
 *   - Only imports "Public holiday" entries — observances are ignored.
 */
class BangladeshHolidayImporter
{
    /**
     * Keywords in the holiday name that make it RELIGIOUS rather than a
     * civil NATIONAL day. Everything Google marks a public holiday that
     * is not on this list is treated as NATIONAL.
     *
     * @var list<string>
     */
    private const RELIGIOUS_KEYWORDS = [
        'eid', 'puja', 'durga', 'nabami', 'dashami', 'janmashtami', 'saraswati',
        'christmas', 'easter', 'good friday', 'buddha', 'purnima', 'madhu',
        'ashura', 'muharram', 'shab-e', 'shab e', 'shab-e-qadr', 'jumatul',
        'milad', 'hijri', 'new year (hijri)',
    ];

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(): array
    {
        $response = Http::timeout(20)
            ->retry(2, 500)
            ->get((string) config('services.google_holidays.bd_ics_url'));

        $response->throw();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $yearStart = Carbon::now()->startOfYear();

        foreach ($this->parsePublicHolidays($response->body()) as $event) {
            if ($event['date']->lt($yearStart)) {
                continue;
            }

            $existing = Holiday::query()->where('external_uid', $event['uid'])->first();

            if ($existing !== null) {
                // Only the facts that genuinely shift year to year — the
                // name and the date. `type` is a best-effort guess on
                // first import; once the row exists, Head HR's own
                // classification wins and a re-sync won't undo it.
                $existing->fill([
                    'title' => $event['title'],
                    'date' => $event['date'],
                ]);

                if ($existing->isDirty(['title', 'date'])) {
                    $updated++;
                }

                $existing->synced_at = Carbon::now();
                $existing->save();

                continue;
            }

            $dateTaken = Holiday::query()
                ->whereDate('date', $event['date']->toDateString())
                ->exists();

            if ($dateTaken) {
                $skipped++;

                continue;
            }

            Holiday::query()->create([
                'title' => $event['title'],
                'date' => $event['date'],
                'type' => $event['type'],
                'description' => 'Bangladesh public holiday (Google calendar).',
                'active' => true,
                'source' => HolidaySource::GoogleBd,
                'external_uid' => $event['uid'],
                'synced_at' => Carbon::now(),
            ]);

            $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @return list<array{uid: string, title: string, date: Carbon, type: HolidayType}>
     */
    private function parsePublicHolidays(string $ics): array
    {
        $lines = explode("\n", $this->unfold($ics));

        $events = [];
        $current = null;

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");

            if ($line === 'BEGIN:VEVENT') {
                $current = [];

                continue;
            }

            if ($line === 'END:VEVENT') {
                if (is_array($current)) {
                    $event = $this->toEvent($current);

                    if ($event !== null) {
                        $events[] = $event;
                    }
                }

                $current = null;

                continue;
            }

            if (! is_array($current)) {
                continue;
            }

            $colon = strpos($line, ':');

            if ($colon === false) {
                continue;
            }

            $name = strtoupper(explode(';', substr($line, 0, $colon))[0]);
            $current[$name] = substr($line, $colon + 1);
        }

        return $events;
    }

    /**
     * @param  array<string, string>  $props
     * @return array{uid: string, title: string, date: Carbon, type: HolidayType}|null
     */
    private function toEvent(array $props): ?array
    {
        $uid = $props['UID'] ?? null;
        $summary = isset($props['SUMMARY']) ? $this->unescape($props['SUMMARY']) : null;
        $description = isset($props['DESCRIPTION']) ? $this->unescape($props['DESCRIPTION']) : '';
        $start = $props['DTSTART'] ?? null;

        if ($uid === null || $summary === null || $start === null) {
            return null;
        }

        if (! Str::contains(Str::lower($description), 'public holiday')) {
            return null;
        }

        if (! preg_match('/(\d{8})/', $start, $matches)) {
            return null;
        }

        return [
            'uid' => $uid,
            'title' => $summary,
            'date' => Carbon::createFromFormat('Ymd', $matches[1])->startOfDay(),
            'type' => $this->classify($summary),
        ];
    }

    private function classify(string $title): HolidayType
    {
        $needle = Str::lower($title);

        foreach (self::RELIGIOUS_KEYWORDS as $keyword) {
            if (Str::contains($needle, $keyword)) {
                return HolidayType::Religious;
            }
        }

        return HolidayType::National;
    }

    /**
     * RFC 5545 line folding: a CRLF followed by a space or tab continues
     * the previous line.
     */
    private function unfold(string $ics): string
    {
        return preg_replace("/\r?\n[ \t]/", '', str_replace("\r\n", "\n", $ics)) ?? $ics;
    }

    private function unescape(string $value): string
    {
        return str_replace(
            ['\\n', '\\N', '\\,', '\\;', '\\\\'],
            ["\n", "\n", ',', ';', '\\'],
            $value,
        );
    }
}
