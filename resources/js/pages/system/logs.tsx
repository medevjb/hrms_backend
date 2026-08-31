import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { logs as logsRoute } from '@/routes/system';
import type { LogFilters, LogPage, TopError } from '@/types/system';
import { formatDateTime, LevelBadge } from './parts';

type PageProps = {
    filters: LogFilters;
    levels: string[];
    result?: LogPage;
    topErrors?: TopError[];
};

const ALL_LEVELS = '__all__';

export default function Logs() {
    const { filters, levels } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (next: Partial<LogFilters>) => {
        const merged = { ...filters, search, ...next, page: next.page ?? 1 };
        router.get(logsRoute().url, cleanParams(merged), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Logs" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Logs"
                    description="Application log, newest first"
                />

                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply({});
                    }}
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="level">Min level</Label>
                        <Select
                            value={filters.level ?? ALL_LEVELS}
                            onValueChange={(value) =>
                                apply({
                                    level:
                                        value === ALL_LEVELS ? null : value,
                                })
                            }
                        >
                            <SelectTrigger id="level" className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL_LEVELS}>
                                    All levels
                                </SelectItem>
                                {levels.map((level) => (
                                    <SelectItem key={level} value={level}>
                                        {level}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="datetime-local"
                            defaultValue={toLocalInput(filters.from)}
                            onChange={(e) =>
                                apply({ from: e.target.value || null })
                            }
                            className="w-52"
                        />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            type="datetime-local"
                            defaultValue={toLocalInput(filters.to)}
                            onChange={(e) =>
                                apply({ to: e.target.value || null })
                            }
                            className="w-52"
                        />
                    </div>

                    <div className="grid flex-1 gap-1.5">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="message or exception text"
                        />
                    </div>

                    <Button type="submit">Filter</Button>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Top errors (last 24h)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Deferred
                            data="topErrors"
                            fallback={<Skeleton className="h-16 w-full" />}
                        >
                            <TopErrors />
                        </Deferred>
                    </CardContent>
                </Card>

                <Deferred
                    data="result"
                    fallback={
                        <div className="space-y-2">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <Skeleton key={i} className="h-12 w-full" />
                            ))}
                        </div>
                    }
                >
                    <Entries onPage={(page) => apply({ page })} />
                </Deferred>
            </div>
        </>
    );
}

function TopErrors() {
    const { topErrors } = usePage<PageProps>().props;

    if (!topErrors || topErrors.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No errors in the last 24 hours.
            </p>
        );
    }

    return (
        <ul className="divide-y">
            {topErrors.map((error) => (
                <li
                    key={error.message}
                    className="flex items-center justify-between gap-4 py-2"
                >
                    <span className="truncate font-mono text-sm">
                        {error.message}
                    </span>
                    <span className="flex shrink-0 items-center gap-2">
                        <LevelBadge level={error.level} />
                        <span className="text-sm font-semibold tabular-nums">
                            {error.count}
                        </span>
                    </span>
                </li>
            ))}
        </ul>
    );
}

function Entries({ onPage }: { onPage: (page: number) => void }) {
    const { result, filters } = usePage<PageProps>().props;

    if (!result) {
        return null;
    }

    if (result.entries.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No log entries match these filters.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {result.truncated && (
                <p className="rounded-md bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                    Results truncated — the scan hit its byte cap. Narrow the
                    time range for a complete view.
                </p>
            )}

            <div className="divide-y rounded-md border">
                {result.entries.map((entry, index) => (
                    <Collapsible key={index}>
                        <div className="flex items-start gap-3 p-3">
                            <LevelBadge level={entry.level} />
                            <div className="min-w-0 flex-1">
                                <p className="font-mono text-sm break-words">
                                    {entry.message}
                                </p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {formatDateTime(entry.logged_at)}
                                    {entry.channel
                                        ? ` · ${entry.channel}`
                                        : ''}
                                </p>
                                {entry.trace && (
                                    <>
                                        <CollapsibleTrigger asChild>
                                            <Button
                                                variant="link"
                                                size="sm"
                                                className="h-auto p-0 text-xs"
                                            >
                                                stack trace
                                            </Button>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <pre className="mt-2 max-h-96 overflow-auto rounded bg-muted p-3 text-xs">
                                                {entry.trace}
                                            </pre>
                                        </CollapsibleContent>
                                    </>
                                )}
                            </div>
                        </div>
                    </Collapsible>
                ))}
            </div>

            <div className="flex items-center justify-between">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={filters.page <= 1}
                    onClick={() => onPage(filters.page - 1)}
                >
                    Newer
                </Button>
                <span className="text-xs text-muted-foreground">
                    page {result.page}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!result.has_more}
                    onClick={() => onPage(filters.page + 1)}
                >
                    Older
                </Button>
            </div>
        </div>
    );
}

function cleanParams(filters: LogFilters): Record<string, string | number> {
    const params: Record<string, string | number> = { page: filters.page };

    if (filters.level) {
        params.level = filters.level;
    }

    if (filters.from) {
        params.from = filters.from;
    }

    if (filters.to) {
        params.to = filters.to;
    }

    if (filters.search) {
        params.search = filters.search;
    }

    return params;
}

function toLocalInput(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

Logs.layout = {
    breadcrumbs: [{ title: 'Logs', href: logsRoute() }],
};
