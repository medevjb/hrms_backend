<?php

namespace App\Support\Logs;

use Inertia\ProvidesScrollMetadata;

/**
 * Feeds Inertia's infinite-scroll cursor from a {@see LogPage}, which is a
 * hand-rolled pager rather than a Laravel paginator.
 */
class LogScrollMetadata implements ProvidesScrollMetadata
{
    /**
     * @param  array{page: int, has_more: bool}  $page
     */
    public function __construct(private readonly array $page) {}

    public function getPageName(): string
    {
        return 'page';
    }

    public function getCurrentPage(): int
    {
        return $this->page['page'];
    }

    public function getPreviousPage(): ?int
    {
        return $this->page['page'] > 1 ? $this->page['page'] - 1 : null;
    }

    public function getNextPage(): ?int
    {
        return $this->page['has_more'] ? $this->page['page'] + 1 : null;
    }
}
