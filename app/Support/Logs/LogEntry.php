<?php

namespace App\Support\Logs;

use Illuminate\Support\Carbon;

/**
 * One parsed application-log entry. A `raw` entry is a line that did not match
 * the Monolog prefix — surfaced, never dropped (docs/PRD.md §79).
 */
class LogEntry
{
    public function __construct(
        public readonly ?Carbon $loggedAt,
        public readonly string $level,
        public readonly ?string $channel,
        public readonly string $message,
        public readonly ?string $trace,
        public readonly bool $raw,
    ) {}

    /**
     * Severity weight for the minimum-level filter. A raw line is treated as
     * ERROR-weight so incident noise is not hidden by a WARNING filter.
     */
    public function severity(): int
    {
        return LogLevel::weight($this->raw ? 'ERROR' : $this->level);
    }

    /**
     * A plain-English sentence for a non-technical reader, or null for entries
     * below WARNING (which need no explaining).
     */
    public function explanation(): ?string
    {
        $level = $this->raw ? 'ERROR' : $this->level;

        if (LogLevel::weight($level) < LogLevel::WEIGHTS['WARNING']) {
            return null;
        }

        return ErrorExplainer::explain($this->message, $this->trace)
            ?? ErrorExplainer::generic($level);
    }

    public function matchesText(string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $haystack = $this->message.' '.(string) $this->trace;

        return mb_stripos($haystack, $needle) !== false;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'logged_at' => $this->loggedAt?->toIso8601String(),
            'level' => $this->raw ? 'RAW' : $this->level,
            'channel' => $this->channel,
            'message' => $this->message,
            'explanation' => $this->explanation(),
            'trace' => $this->trace,
            'raw' => $this->raw,
        ];
    }
}
