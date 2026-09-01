import {
    Deferred,
    Head,
    InfiniteScroll,
    router,
    usePage,
} from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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
import { Spinner } from '@/components/ui/spinner';
import { logs as logsRoute } from '@/routes/system';
import type {
    ActivityBucket,
    LogEntry,
    LogFilters,
    LogPage,
    TopError,
} from '@/types/system';
import { ActivityChart } from './charts';
import { formatDateTime, LevelRail, Panel, TopErrorList } from './parts';

type PageProps = {
    filters: LogFilters;
    levels: string[];
    result: LogPage;
    activity?: ActivityBucket[];
    topErrors?: TopError[];
};

const ALL_LEVELS = '__all__';

const labelClass = 'text-[11px] tracking-wide text-muted-foreground uppercase';

export default function Logs() {
    const { filters, levels } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (next: Partial<LogFilters>) => {
        const merged = { ...filters, search, ...next, page: 1 };
        router.get(logsRoute().url, cleanParams(merged), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Logs" />

            <div className="mx-auto max-w-4xl space-y-8">
                <Heading
                    variant="small"
                    title="Logs"
                    description="Everything the app has written, newest first — in plain language, with the technical detail one click away."
                />

                <form
                    className="flex flex-wrap items-end gap-3 rounded-xl border bg-muted/30 p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters({});
                    }}
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="level" className={labelClass}>
                            Min level
                        </Label>
                        <Select
                            value={filters.level ?? ALL_LEVELS}
                            onValueChange={(value) =>
                                applyFilters({
                                    level: value === ALL_LEVELS ? null : value,
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
                        <Label htmlFor="from" className={labelClass}>
                            From
                        </Label>
                        <Input
                            id="from"
                            type="datetime-local"
                            defaultValue={toLocalInput(filters.from)}
                            onChange={(e) =>
                                applyFilters({ from: e.target.value || null })
                            }
                            className="w-52"
                        />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="to" className={labelClass}>
                            To
                        </Label>
                        <Input
                            id="to"
                            type="datetime-local"
                            defaultValue={toLocalInput(filters.to)}
                            onChange={(e) =>
                                applyFilters({ to: e.target.value || null })
                            }
                            className="w-52"
                        />
                    </div>

                    <div className="grid flex-1 gap-1.5">
                        <Label htmlFor="search" className={labelClass}>
                            Search
                        </Label>
                        <Input
                            id="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Message or error text"
                        />
                    </div>

                    <Button type="submit">Filter</Button>
                </form>

                <Panel title="Activity — last 24 hours">
                    <Deferred
                        data="activity"
                        fallback={<Skeleton className="h-44 w-full" />}
                    >
                        <Activity />
                    </Deferred>
                </Panel>

                <Panel title="Errors — last 24 hours">
                    <Deferred
                        data="topErrors"
                        fallback={<Skeleton className="h-16 w-full" />}
                    >
                        <TopErrors />
                    </Deferred>
                </Panel>

                <Panel title="Log entries">
                    <Entries />
                </Panel>
            </div>
        </>
    );
}

function Activity() {
    const { activity } = usePage<PageProps>().props;

    return <ActivityChart buckets={activity ?? []} />;
}

function TopErrors() {
    const { topErrors } = usePage<PageProps>().props;

    return (
        <TopErrorList
            errors={topErrors ?? []}
            emptyMessage="No errors in the last 24 hours."
        />
    );
}

function Entries() {
    const { result } = usePage<PageProps>().props;

    if (result.entries.length === 0) {
        return (
            <p className="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                No log entries match these filters. Widen the time range or
                clear the search.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {result.truncated && (
                <p className="rounded-md bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                    Showing the most recent entries only — the log is larger
                    than the scan limit. Narrow the time range for a complete
                    view.
                </p>
            )}

            <InfiniteScroll
                data="result"
                itemsElement="#log-entries"
                buffer={600}
                manualAfter={5}
                next={({ loading, fetch, manualMode, hasMore }) =>
                    !hasMore ? (
                        <p className="py-3 text-center text-xs text-muted-foreground">
                            End of the log.
                        </p>
                    ) : loading ? (
                        <span className="flex items-center justify-center gap-2 py-3 text-xs text-muted-foreground">
                            <Spinner className="size-3" /> Loading older
                            entries…
                        </span>
                    ) : manualMode ? (
                        <div className="flex justify-center py-3">
                            <Button variant="outline" size="sm" onClick={fetch}>
                                Load older entries
                            </Button>
                        </div>
                    ) : null
                }
            >
                <div
                    id="log-entries"
                    className="divide-y overflow-hidden rounded-xl border"
                >
                    {result.entries.map((entry, index) => (
                        <LogRow
                            key={`${entry.logged_at ?? 'na'}-${index}`}
                            entry={entry}
                        />
                    ))}
                </div>
            </InfiniteScroll>
        </div>
    );
}

function LogRow({ entry }: { entry: LogEntry }) {
    return (
        <Collapsible>
            <div className="flex items-stretch">
                <LevelRail level={entry.level} />
                <div className="min-w-0 flex-1 px-4 py-3">
                    {entry.explanation && (
                        <p className="text-sm font-medium">
                            {entry.explanation}
                        </p>
                    )}
                    <p
                        className={
                            entry.explanation
                                ? 'mt-1 font-mono text-xs break-words text-muted-foreground'
                                : 'font-mono text-sm break-words'
                        }
                    >
                        {entry.message}
                    </p>
                    <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                        <span className="font-mono">
                            {formatDateTime(entry.logged_at)}
                        </span>
                        {entry.channel && (
                            <>
                                <span aria-hidden>·</span>
                                <span>{entry.channel}</span>
                            </>
                        )}
                        {entry.trace && (
                            <>
                                <span aria-hidden>·</span>
                                <CollapsibleTrigger asChild>
                                    <Button
                                        variant="link"
                                        size="sm"
                                        className="h-auto p-0 text-xs"
                                    >
                                        Technical detail
                                    </Button>
                                </CollapsibleTrigger>
                            </>
                        )}
                    </div>
                    {entry.trace && (
                        <CollapsibleContent>
                            <pre className="mt-2 max-h-96 overflow-auto rounded bg-muted p-3 text-xs">
                                {entry.trace}
                            </pre>
                        </CollapsibleContent>
                    )}
                </div>
            </div>
        </Collapsible>
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
