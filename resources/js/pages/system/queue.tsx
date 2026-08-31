import { Deferred, Head, router, usePage } from '@inertiajs/react';
import QueueController from '@/actions/App/Http/Controllers/System/QueueController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Skeleton } from '@/components/ui/skeleton';
import { queue as queueRoute } from '@/routes/system';
import type { FailedJob, Paginated, QueueDepth } from '@/types/system';
import { formatAge, formatDateTime, Tile } from './parts';

type PageProps = {
    depth: QueueDepth;
    failed?: Paginated<FailedJob>;
};

export default function Queue() {
    const { depth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Queue" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Queue"
                    description="Database queue depth and failed jobs"
                />

                {depth.available ? (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Tile
                            label="Pending"
                            value={depth.total_pending}
                            tone={depth.total_pending > 0 ? 'warn' : 'ok'}
                        />
                        <Tile
                            label="Oldest pending"
                            value={formatAge(depth.oldest_pending_age_seconds)}
                        />
                        <Tile
                            label="Queues"
                            value={
                                Object.keys(depth.by_queue).length || '—'
                            }
                            hint={Object.entries(depth.by_queue)
                                .map(([q, n]) => `${q}: ${n}`)
                                .join(', ')}
                        />
                    </div>
                ) : (
                    <p className="rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                        Queue-depth detail is unavailable for the{' '}
                        <span className="font-mono">{depth.connection}</span>{' '}
                        connection.
                    </p>
                )}

                <Deferred
                    data="failed"
                    fallback={
                        <div className="space-y-2">
                            {Array.from({ length: 4 }).map((_, i) => (
                                <Skeleton key={i} className="h-14 w-full" />
                            ))}
                        </div>
                    }
                >
                    <FailedJobs />
                </Deferred>
            </div>
        </>
    );
}

function FailedJobs() {
    const { failed } = usePage<PageProps>().props;

    if (!failed) {
        return null;
    }

    const goToPage = (page: number) =>
        router.get(queueRoute().url, { page }, { preserveScroll: true });

    return (
        <section className="space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium">
                    Failed jobs ({failed.meta.total})
                </h3>
                {failed.meta.total > 0 && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.post(QueueController.retryAll().url, undefined, {
                                preserveScroll: true,
                            })
                        }
                    >
                        Retry all
                    </Button>
                )}
            </div>

            {failed.data.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No failed jobs.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {failed.data.map((job) => (
                        <Collapsible key={job.uuid}>
                            <div className="flex items-start gap-3 p-3">
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium">
                                        {job.display_name ?? job.queue}
                                    </p>
                                    <p className="font-mono text-xs break-words text-muted-foreground">
                                        {job.exception_summary}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {job.connection} · {job.queue} ·{' '}
                                        {formatDateTime(job.failed_at)} ·{' '}
                                        <span className="font-mono">
                                            {job.uuid}
                                        </span>
                                    </p>
                                    <CollapsibleTrigger asChild>
                                        <Button
                                            variant="link"
                                            size="sm"
                                            className="h-auto p-0 text-xs"
                                        >
                                            exception
                                        </Button>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <pre className="mt-2 max-h-96 overflow-auto rounded bg-muted p-3 text-xs">
                                            {job.exception}
                                        </pre>
                                    </CollapsibleContent>
                                </div>
                                <div className="flex shrink-0 flex-col gap-1">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                QueueController.retry(job.uuid)
                                                    .url,
                                                undefined,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Retry
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            router.post(
                                                QueueController.forget(
                                                    job.uuid,
                                                ).url,
                                                undefined,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Forget
                                    </Button>
                                </div>
                            </div>
                        </Collapsible>
                    ))}
                </div>
            )}

            {failed.meta.last_page > 1 && (
                <div className="flex items-center justify-between">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={failed.meta.current_page <= 1}
                        onClick={() => goToPage(failed.meta.current_page - 1)}
                    >
                        Previous
                    </Button>
                    <span className="text-xs text-muted-foreground">
                        page {failed.meta.current_page} of{' '}
                        {failed.meta.last_page}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={
                            failed.meta.current_page >= failed.meta.last_page
                        }
                        onClick={() => goToPage(failed.meta.current_page + 1)}
                    >
                        Next
                    </Button>
                </div>
            )}
        </section>
    );
}

Queue.layout = {
    breadcrumbs: [{ title: 'Queue', href: queueRoute() }],
};
