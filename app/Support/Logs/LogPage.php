<?php

namespace App\Support\Logs;

/**
 * A page of log-viewer results plus the flags the UI needs: whether older
 * matches exist, and whether the scan hit its byte cap before finishing.
 */
class LogPage
{
    /**
     * @param  list<LogEntry>  $entries
     */
    public function __construct(
        public readonly array $entries,
        public readonly int $page,
        public readonly int $perPage,
        public readonly bool $hasMore,
        public readonly bool $truncated,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'entries' => array_map(fn (LogEntry $e) => $e->toArray(), $this->entries),
            'page' => $this->page,
            'per_page' => $this->perPage,
            'has_more' => $this->hasMore,
            'truncated' => $this->truncated,
        ];
    }
}
