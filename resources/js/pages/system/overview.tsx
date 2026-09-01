import { Deferred, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard } from '@/routes';
import type { ActivityBucket, HealthSnapshot, TopError } from '@/types/system';
import { ActivityChart, SystemTopology } from './charts';
import {
    Field,
    formatClock,
    Panel,
    Readout,
    ServiceRow,
    StatusBand,
    TopErrorList,
} from './parts';
import type { Indicator, Tone } from './parts';

type PageProps = {
    health: HealthSnapshot;
    activity?: ActivityBucket[];
    topErrors?: TopError[];
};

type ServiceView = {
    name: string;
    tone: Tone;
    summary: string;
    detail?: string;
    metric?: string;
};

function probeTone(status: string): Tone {
    if (status === 'ok') {
        return 'ok';
    }

    if (status === 'error') {
        return 'bad';
    }

    return 'warn';
}

function describeDatabase(db: HealthSnapshot['database']): ServiceView {
    if (db.status === 'ok') {
        return {
            name: 'Database',
            tone: 'ok',
            summary: 'Connected and responding to queries.',
            metric:
                db.latency_ms !== undefined ? `${db.latency_ms} ms` : undefined,
            detail: db.connection ? `connection: ${db.connection}` : undefined,
        };
    }

    return {
        name: 'Database',
        tone: 'bad',
        summary: "The app can't reach the database right now.",
        detail: db.message,
    };
}

function describeStorage(store: HealthSnapshot['local_storage']): ServiceView {
    if (store.status === 'ok') {
        return {
            name: 'File storage',
            tone: 'ok',
            summary: 'Files can be read and written.',
            detail: store.disk ? `disk: ${store.disk}` : undefined,
        };
    }

    return {
        name: 'File storage',
        tone: 'bad',
        summary: "The app can't read or write files on this server.",
        detail: store.message,
    };
}

function describeScheduler(sched: HealthSnapshot['scheduler']): ServiceView {
    if (sched.status === 'unknown') {
        return {
            name: 'Scheduler',
            tone: 'warn',
            summary:
                'No run has been recorded yet. Check that the scheduler is running on the server.',
        };
    }

    const ago =
        sched.minutes_ago === undefined
            ? 'recently'
            : sched.minutes_ago < 1
              ? 'less than a minute ago'
              : `${sched.minutes_ago} min ago`;

    if (sched.status === 'ok') {
        return {
            name: 'Scheduler',
            tone: 'ok',
            summary: `Ran ${ago}. Background schedules are firing on time.`,
        };
    }

    return {
        name: 'Scheduler',
        tone: 'warn',
        summary: `Last ran ${ago} — longer than expected. The scheduler may have stopped.`,
    };
}

function workerTone(status: string): Tone {
    return status === 'ok'
        ? 'ok'
        : status === 'error'
          ? 'bad'
          : 'warn';
}

/** Short phrase for the worker's heartbeat state — reused across the page. */
function workerPhrase(worker: HealthSnapshot['queue']['worker']): string {
    if (worker.status === 'ok') {
        return 'running';
    }

    if (worker.status === 'stale') {
        return 'not responding';
    }

    if (worker.status === 'error') {
        return 'stopped';
    }

    return 'unknown';
}

function describeQueue(queue: HealthSnapshot['queue']): ServiceView {
    const { worker } = queue;

    const ago =
        worker.minutes_ago === undefined
            ? 'a while ago'
            : worker.minutes_ago < 1
              ? 'less than a minute ago'
              : `${worker.minutes_ago} min ago`;

    const head =
        worker.status === 'ok'
            ? `A worker is running — last heartbeat ${ago}.`
            : worker.status === 'stale'
              ? `No heartbeat since ${ago}. The worker may have stopped.`
              : worker.status === 'error'
                ? `No heartbeat since ${ago}. No worker is processing jobs.`
                : 'No worker heartbeat yet. Start a queue worker, or check the scheduler.';

    const jobs =
        queue.pending_jobs === 0
            ? 'Nothing is waiting.'
            : `${queue.pending_jobs} job${queue.pending_jobs === 1 ? '' : 's'} waiting.`;

    const failed =
        queue.failed_jobs > 0
            ? ` ${queue.failed_jobs} failed and need attention.`
            : '';

    return {
        name: 'Queue worker',
        tone:
            queue.failed_jobs > 0 || worker.status === 'error'
                ? 'bad'
                : worker.status === 'ok'
                  ? 'ok'
                  : 'warn',
        summary: `${head} ${jobs}${failed}`,
        detail: `connection: ${queue.connection}`,
        metric: `worker ${workerPhrase(worker)} · ${queue.pending_jobs} waiting · ${queue.failed_jobs} failed`,
    };
}

const toneRank: Record<Tone, number> = { ok: 0, muted: 0, warn: 1, bad: 2 };

export default function Overview() {
    const { health } = usePage<PageProps>().props;

    const services = [
        describeDatabase(health.database),
        describeStorage(health.local_storage),
        describeScheduler(health.scheduler),
        describeQueue(health.queue),
    ];

    const errorTone: Tone = health.errors_24h > 0 ? 'warn' : 'ok';
    const worst = [...services.map((s) => s.tone), errorTone].reduce<
        'ok' | 'warn' | 'bad'
    >(
        (acc, tone) =>
            tone !== 'muted' && toneRank[tone] > toneRank[acc] ? tone : acc,
        'ok',
    );

    const troubled = services.filter(
        (s) => s.tone === 'warn' || s.tone === 'bad',
    );

    const verdict =
        worst === 'ok'
            ? 'All systems operational'
            : worst === 'warn'
              ? 'Running, with warnings'
              : 'Needs attention';

    const detail =
        worst === 'ok'
            ? 'Every check passed. Nothing needs your attention right now.'
            : troubled.length > 0
              ? `Check: ${troubled.map((s) => s.name.toLowerCase()).join(', ')}${
                    health.errors_24h > 0 ? ', recent errors' : ''
                }.`
              : `${health.errors_24h} error${health.errors_24h === 1 ? '' : 's'} logged in the last 24 hours.`;

    const indicators: Indicator[] = [
        {
            label: 'db',
            value: health.database.status,
            tone: probeTone(health.database.status),
        },
        {
            label: 'storage',
            value: health.local_storage.status,
            tone: probeTone(health.local_storage.status),
        },
        {
            label: 'scheduler',
            value: health.scheduler.status,
            tone: probeTone(health.scheduler.status),
        },
        {
            label: 'worker',
            value: workerPhrase(health.queue.worker),
            tone: workerTone(health.queue.worker.status),
        },
        {
            label: 'queued',
            value: String(health.queue.pending_jobs),
            tone:
                health.queue.pending_jobs > 0 &&
                health.queue.worker.status !== 'ok'
                    ? 'warn'
                    : 'ok',
        },
        {
            label: 'failed',
            value: String(health.queue.failed_jobs),
            tone: health.queue.failed_jobs > 0 ? 'bad' : 'ok',
        },
        {
            label: 'errors/24h',
            value: String(health.errors_24h),
            tone: health.errors_24h > 0 ? 'warn' : 'ok',
        },
    ];

    return (
        <>
            <Head title="System overview" />

            <div className="mx-auto max-w-6xl space-y-8">
                <Heading
                    variant="small"
                    title="Overview"
                    description="Live health of the platform and everything it runs on."
                />

                <StatusBand
                    tone={worst}
                    verdict={verdict}
                    detail={detail}
                    meta={`checked ${formatClock(health.checked_at)}`}
                    indicators={indicators}
                />

                <div className="grid gap-8 lg:grid-cols-[1.55fr_1fr]">
                    <Panel title="Connections">
                        <div className="rounded-xl border bg-muted/20 p-4">
                            <SystemTopology health={health} />
                        </div>
                    </Panel>

                    <Panel title="Signals">
                        <div>
                            <Readout
                                value={
                                    health.database.latency_ms !== undefined
                                        ? `${health.database.latency_ms} ms`
                                        : '—'
                                }
                                label="db round-trip"
                                tone={probeTone(health.database.status)}
                                note={
                                    health.database.status === 'ok'
                                        ? 'A test query returned well within budget.'
                                        : 'The database did not answer a test query.'
                                }
                            />
                            <Readout
                                value={workerPhrase(health.queue.worker)}
                                label="queue worker"
                                tone={workerTone(health.queue.worker.status)}
                                note={
                                    health.queue.worker.status === 'ok'
                                        ? `Processing jobs — ${health.queue.pending_jobs} waiting.`
                                        : health.queue.worker.status ===
                                            'unknown'
                                          ? 'No heartbeat recorded yet — no worker has run.'
                                          : `No heartbeat lately — ${health.queue.pending_jobs} job(s) stuck in the queue.`
                                }
                            />
                            <Readout
                                value={health.queue.failed_jobs}
                                label="jobs failed"
                                tone={
                                    health.queue.failed_jobs > 0 ? 'bad' : 'ok'
                                }
                                note={
                                    health.queue.failed_jobs > 0
                                        ? 'Failed jobs stay until someone retries or clears them.'
                                        : 'No background job has been given up on.'
                                }
                            />
                            <Readout
                                value={health.errors_24h}
                                label="errors / 24h"
                                tone={health.errors_24h > 0 ? 'warn' : 'ok'}
                                note={
                                    health.errors_24h > 0
                                        ? 'See the breakdown below for what went wrong.'
                                        : 'No error-level entries in the last day.'
                                }
                            />
                        </div>
                    </Panel>
                </div>

                <Panel title="Log activity — last 24 hours">
                    <Deferred
                        data="activity"
                        fallback={<Skeleton className="h-52 w-full" />}
                    >
                        <Activity />
                    </Deferred>
                </Panel>

                <div className="grid gap-8 md:grid-cols-[1.4fr_1fr]">
                    <Panel title="Services">
                        <div>
                            {services.map((service) => (
                                <ServiceRow key={service.name} {...service} />
                            ))}
                        </div>
                    </Panel>

                    <Panel title="Platform">
                        <div>
                            <Field
                                label="Environment"
                                value={health.environment}
                                tone={
                                    health.environment === 'production'
                                        ? 'muted'
                                        : 'warn'
                                }
                            />
                            <Field
                                label="App version"
                                value={health.application_version}
                            />
                            <Field
                                label="Laravel"
                                value={health.laravel_version}
                            />
                            <Field label="PHP" value={health.php_version} />
                        </div>
                    </Panel>
                </div>

                <Panel
                    title="Errors — last 24 hours"
                    aside={
                        <span className="font-mono text-2xl font-semibold tabular-nums">
                            {health.errors_24h}
                        </span>
                    }
                >
                    <Deferred
                        data="topErrors"
                        fallback={
                            <div className="space-y-2">
                                <Skeleton className="h-2 w-full" />
                                <Skeleton className="h-9 w-full" />
                                <Skeleton className="h-9 w-2/3" />
                            </div>
                        }
                    >
                        <TopErrors />
                    </Deferred>
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

    return <TopErrorList errors={topErrors ?? []} />;
}

Overview.layout = {
    breadcrumbs: [{ title: 'System overview', href: dashboard() }],
};
