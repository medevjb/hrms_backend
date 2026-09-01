<?php

namespace App\Services;

use App\Support\Logs\ErrorExplainer;
use App\Support\Logs\LogEntry;
use App\Support\Logs\LogLevel;
use App\Support\Logs\LogPage;
use App\Support\Logs\LogQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * docs/PRD.md §79 — reads the application log file into structured, filterable
 * entries for the console's Logs page. Never reads more than
 * `system-console.logs.max_scan_bytes`: on a larger file it scans only that
 * many bytes from the tail and reports the result as truncated.
 */
class LogReader
{
    /** Hard ceiling on retained entries so a huge window cannot exhaust memory. */
    private const ENTRY_CEILING = 20000;

    private const HEADER = '/^\[(?<datetime>\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]\s+(?<channel>[\w.-]+)\.(?<level>[A-Z]+):\s?(?<message>.*)$/';

    public function __construct(
        private readonly string $path,
        private readonly int $maxScanBytes,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('system-console.logs.channel_path'),
            (int) config('system-console.logs.max_scan_bytes'),
        );
    }

    public function query(LogQuery $filter): LogPage
    {
        [$entries, $truncated] = $this->read();

        $matches = array_values(array_filter($entries, $filter->accepts(...)));

        // Newest first.
        $matches = array_reverse($matches);

        $offset = ($filter->page - 1) * $filter->perPage;
        $window = array_slice($matches, $offset, $filter->perPage);

        return new LogPage(
            entries: $window,
            page: $filter->page,
            perPage: $filter->perPage,
            hasMore: count($matches) > $offset + $filter->perPage,
            truncated: $truncated,
        );
    }

    public function errorCountSince(Carbon $since): int
    {
        [$entries] = $this->read();

        return count(array_filter(
            $entries,
            fn (LogEntry $e) => LogLevel::isErrorOrHigher($e->raw ? 'ERROR' : $e->level)
                && $e->loggedAt !== null
                && $e->loggedAt->gte($since),
        ));
    }

    /**
     * Bucketed log activity for the console's 24-hour charts: one entry per
     * hour from `$since` to now, oldest first, tallied by severity band.
     *
     * @return list<array{start: string, total: int, info: int, warning: int, error: int}>
     */
    public function histogram(Carbon $since, int $buckets = 24, int $minutesPerBucket = 60): array
    {
        [$entries] = $this->read();

        $since = $since->copy()->startOfMinute();
        $spanSeconds = $buckets * $minutesPerBucket * 60;

        /** @var list<array{start: string, total: int, info: int, warning: int, error: int}> $out */
        $out = [];
        for ($i = 0; $i < $buckets; $i++) {
            $out[$i] = [
                'start' => $since->copy()->addMinutes($i * $minutesPerBucket)->toIso8601String(),
                'total' => 0,
                'info' => 0,
                'warning' => 0,
                'error' => 0,
            ];
        }

        foreach ($entries as $entry) {
            if ($entry->loggedAt === null || $entry->loggedAt->lt($since)) {
                continue;
            }

            $offset = $entry->loggedAt->getTimestamp() - $since->getTimestamp();
            if ($offset < 0 || $offset >= $spanSeconds) {
                continue;
            }

            $index = intdiv($offset, $minutesPerBucket * 60);
            $level = $entry->raw ? 'ERROR' : $entry->level;

            $band = match (true) {
                LogLevel::isErrorOrHigher($level) => 'error',
                LogLevel::weight($level) >= LogLevel::WEIGHTS['WARNING'] => 'warning',
                default => 'info',
            };

            $out[$index]['total']++;
            $out[$index][$band]++;
        }

        return array_values($out);
    }

    /**
     * @return list<array{message: string, count: int, level: string, last_seen: string|null, explanation: string}>
     */
    public function topErrors(Carbon $since, int $limit = 10): array
    {
        [$entries] = $this->read();

        $groups = [];

        foreach ($entries as $entry) {
            $level = $entry->raw ? 'ERROR' : $entry->level;

            if (! LogLevel::isErrorOrHigher($level) || $entry->loggedAt === null || $entry->loggedAt->lt($since)) {
                continue;
            }

            $key = $this->mask($entry->message);

            if (! isset($groups[$key])) {
                $groups[$key] = ['message' => $key, 'count' => 0, 'level' => $level, 'last_seen' => null];
            }

            $groups[$key]['count']++;

            if (LogLevel::weight($level) > LogLevel::weight($groups[$key]['level'])) {
                $groups[$key]['level'] = $level;
            }

            $seen = $entry->loggedAt->toIso8601String();
            if ($groups[$key]['last_seen'] === null || $seen > $groups[$key]['last_seen']) {
                $groups[$key]['last_seen'] = $seen;
            }
        }

        return array_values(
            (new Collection($groups))
                ->sortByDesc('count')
                ->take($limit)
                ->map(fn (array $group): array => [
                    ...$group,
                    'explanation' => ErrorExplainer::explain($group['message'])
                        ?? ErrorExplainer::generic($group['level']),
                ])
                ->all()
        );
    }

    /**
     * Reads the tail of the log file (bounded by maxScanBytes) into entries in
     * file order.
     *
     * @return array{0: list<LogEntry>, 1: bool} entries and whether the scan was truncated
     */
    private function read(): array
    {
        if (! is_file($this->path) || ! is_readable($this->path)) {
            return [[], false];
        }

        $size = filesize($this->path) ?: 0;
        $start = max(0, $size - $this->maxScanBytes);
        $truncated = $start > 0;

        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            return [[], false];
        }

        if ($start > 0) {
            fseek($handle, $start);
            fgets($handle); // discard the partial line we landed in the middle of
        }

        /** @var list<LogEntry> $entries */
        $entries = [];
        $current = null; // array{datetime,channel,level,message,trace: list<string>}

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            if (preg_match(self::HEADER, $line, $m)) {
                if ($current !== null) {
                    $entries[] = $this->buildEntry($current);
                }

                $current = [
                    'datetime' => $m['datetime'],
                    'channel' => $m['channel'],
                    'level' => strtoupper($m['level']),
                    'message' => $m['message'],
                    'trace' => [],
                ];

                continue;
            }

            if ($current !== null) {
                $current['trace'][] = $line;

                continue;
            }

            if (trim($line) !== '') {
                $entries[] = new LogEntry(null, 'RAW', null, $line, null, true);
            }

            if (count($entries) > self::ENTRY_CEILING) {
                array_shift($entries);
                $truncated = true;
            }
        }

        if ($current !== null) {
            $entries[] = $this->buildEntry($current);
        }

        fclose($handle);

        if (count($entries) > self::ENTRY_CEILING) {
            $entries = array_slice($entries, -self::ENTRY_CEILING);
            $truncated = true;
        }

        return [$entries, $truncated];
    }

    /**
     * @param  array{datetime: string, channel: string, level: string, message: string, trace: list<string>}  $parts
     */
    private function buildEntry(array $parts): LogEntry
    {
        $trace = trim(implode("\n", $parts['trace']));

        try {
            $loggedAt = Carbon::parse($parts['datetime']);
        } catch (\Throwable) {
            $loggedAt = null;
        }

        return new LogEntry(
            loggedAt: $loggedAt,
            level: $parts['level'],
            channel: $parts['channel'],
            message: $parts['message'],
            trace: $trace === '' ? null : $trace,
            raw: false,
        );
    }

    /**
     * Collapses the volatile parts of a message so repeated errors group into
     * one row: UUIDs, numbers, and quoted fragments become placeholders.
     */
    private function mask(string $message): string
    {
        $message = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '{uuid}', $message) ?? $message;
        $message = preg_replace('/"[^"]*"/', '"{}"', $message) ?? $message;
        $message = preg_replace("/'[^']*'/", "'{}'", $message) ?? $message;
        $message = preg_replace('/\b\d+\b/', '{n}', $message) ?? $message;

        return trim($message);
    }
}
