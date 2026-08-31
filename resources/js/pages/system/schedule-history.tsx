import { Head, Link, router, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { schedule as scheduleRoute } from '@/routes/system';
import { show as scheduleShow } from '@/routes/system/schedule';
import type { ScheduleHistory } from '@/types/system';
import { formatDateTime, formatDuration, StatusDot } from './parts';

type PageProps = {
    history: ScheduleHistory;
};

export default function ScheduleHistoryPage() {
    const { history } = usePage<PageProps>().props;

    const goToPage = (page: number) =>
        router.get(
            scheduleShow(history.command).url,
            { page },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={`Schedule · ${history.command}`} />

            <div className="space-y-6">
                <div className="space-y-1">
                    <Link
                        href={scheduleRoute().url}
                        className="text-xs text-muted-foreground hover:underline"
                    >
                        ← All commands
                    </Link>
                    <Heading
                        variant="small"
                        title={history.command}
                        description={
                            history.is_registered
                                ? 'Run history'
                                : 'Run history · no longer registered in the scheduler'
                        }
                    />
                </div>

                {history.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No recorded runs for this command.
                    </p>
                ) : (
                    <div className="divide-y rounded-md border">
                        {history.data.map((run) => (
                            <Collapsible key={run.id}>
                                <div className="flex items-start gap-3 p-3">
                                    <div className="min-w-0 flex-1">
                                        <StatusDot status={run.status} />
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {formatDateTime(run.started_at)} ·{' '}
                                            {formatDuration(run.duration_ms)}
                                            {run.exit_code !== null
                                                ? ` · exit ${run.exit_code}`
                                                : ''}
                                        </p>
                                        {run.output && (
                                            <>
                                                <CollapsibleTrigger asChild>
                                                    <Button
                                                        variant="link"
                                                        size="sm"
                                                        className="h-auto p-0 text-xs"
                                                    >
                                                        output
                                                    </Button>
                                                </CollapsibleTrigger>
                                                <CollapsibleContent>
                                                    <pre className="mt-2 max-h-96 overflow-auto rounded bg-muted p-3 text-xs">
                                                        {run.output}
                                                    </pre>
                                                </CollapsibleContent>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </Collapsible>
                        ))}
                    </div>
                )}

                {history.meta.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={history.meta.current_page <= 1}
                            onClick={() =>
                                goToPage(history.meta.current_page - 1)
                            }
                        >
                            Previous
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            page {history.meta.current_page} of{' '}
                            {history.meta.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={
                                history.meta.current_page >=
                                history.meta.last_page
                            }
                            onClick={() =>
                                goToPage(history.meta.current_page + 1)
                            }
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

ScheduleHistoryPage.layout = {
    breadcrumbs: [{ title: 'Schedule', href: scheduleRoute() }],
};
