import { Deferred, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard } from '@/routes';
import type { HealthSnapshot, TopError } from '@/types/system';
import { formatDateTime, LevelBadge, Tile } from './parts';

type PageProps = {
    health: HealthSnapshot;
    topErrors?: TopError[];
};

function probeTone(status: string): 'ok' | 'warn' | 'bad' {
    if (status === 'ok') {
        return 'ok';
    }

    if (status === 'error') {
        return 'bad';
    }

    return 'warn';
}

export default function Overview() {
    const { health } = usePage<PageProps>().props;

    return (
        <>
            <Head title="System overview" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Overview"
                    description={`Snapshot taken ${formatDateTime(health.checked_at)}`}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <Tile label="Environment" value={health.environment} />
                    <Tile label="App version" value={health.application_version} />
                    <Tile
                        label="Laravel / PHP"
                        value={`${health.laravel_version}`}
                        hint={`PHP ${health.php_version}`}
                    />
                    <Tile
                        label="Database"
                        value={health.database.status}
                        tone={probeTone(health.database.status)}
                        hint={
                            health.database.message ??
                            (health.database.latency_ms !== undefined
                                ? `${health.database.latency_ms} ms · ${health.database.connection}`
                                : undefined)
                        }
                    />
                    <Tile
                        label="Local storage"
                        value={health.local_storage.status}
                        tone={probeTone(health.local_storage.status)}
                        hint={health.local_storage.message}
                    />
                    <Tile
                        label="Scheduler"
                        value={health.scheduler.status}
                        tone={probeTone(health.scheduler.status)}
                        hint={
                            health.scheduler.minutes_ago !== undefined
                                ? `last heartbeat ${health.scheduler.minutes_ago}m ago`
                                : 'no heartbeat seen'
                        }
                    />
                    <Tile
                        label="Queue pending"
                        value={health.queue.pending_jobs}
                        tone={health.queue.pending_jobs > 0 ? 'warn' : 'ok'}
                        hint={`connection: ${health.queue.connection}`}
                    />
                    <Tile
                        label="Failed jobs"
                        value={health.queue.failed_jobs}
                        tone={health.queue.failed_jobs > 0 ? 'bad' : 'ok'}
                    />
                    <Tile
                        label="Errors (24h)"
                        value={health.errors_24h}
                        tone={health.errors_24h > 0 ? 'bad' : 'ok'}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Top errors (last 24h)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Deferred
                            data="topErrors"
                            fallback={
                                <div className="space-y-2">
                                    <Skeleton className="h-8 w-full" />
                                    <Skeleton className="h-8 w-full" />
                                    <Skeleton className="h-8 w-2/3" />
                                </div>
                            }
                        >
                            <TopErrors />
                        </Deferred>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function TopErrors() {
    const { topErrors } = usePage<PageProps>().props;

    if (!topErrors || topErrors.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No errors logged in the last 24 hours.
            </p>
        );
    }

    return (
        <ul className="divide-y">
            {topErrors.map((error) => (
                <li
                    key={error.message}
                    className="flex items-start justify-between gap-4 py-2"
                >
                    <div className="min-w-0">
                        <p className="truncate font-mono text-sm">
                            {error.message}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            last seen {formatDateTime(error.last_seen)}
                        </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <LevelBadge level={error.level} />
                        <span className="text-sm font-semibold tabular-nums">
                            {error.count}
                        </span>
                    </div>
                </li>
            ))}
        </ul>
    );
}

Overview.layout = {
    breadcrumbs: [{ title: 'System overview', href: dashboard() }],
};
