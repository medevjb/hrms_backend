<?php

namespace App\Support\Logs;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The log viewer's filter state, combinable: minimum level, time range, and a
 * substring search over message + trace, plus the page window.
 */
class LogQuery
{
    public function __construct(
        public readonly ?string $minLevel = null,
        public readonly ?Carbon $from = null,
        public readonly ?Carbon $to = null,
        public readonly string $search = '',
        public readonly int $page = 1,
        public readonly int $perPage = 50,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $level = strtoupper((string) $request->string('level'));

        return new self(
            minLevel: in_array($level, LogLevel::names(), true) ? $level : null,
            from: self::parseDate($request->input('from')),
            to: self::parseDate($request->input('to')),
            search: trim((string) $request->string('search')),
            page: max(1, $request->integer('page', 1)),
            perPage: min(200, max(1, $request->integer('per_page', 50))),
        );
    }

    public function accepts(LogEntry $entry): bool
    {
        if ($this->minLevel !== null && $entry->severity() < LogLevel::weight($this->minLevel)) {
            return false;
        }

        if ($this->from !== null && ($entry->loggedAt === null || $entry->loggedAt->lt($this->from))) {
            return false;
        }

        if ($this->to !== null && ($entry->loggedAt === null || $entry->loggedAt->gt($this->to))) {
            return false;
        }

        return $entry->matchesText($this->search);
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
