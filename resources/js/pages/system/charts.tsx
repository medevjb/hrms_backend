import { cn } from '@/lib/utils';
import type { ActivityBucket, HealthSnapshot } from '@/types/system';
import type { Tone } from './parts';

/* -------------------------------------------------------------------------- */
/*  Activity chart — 24h of log volume, stacked by severity.                   */
/* -------------------------------------------------------------------------- */

const PLOT = { w: 720, h: 200, top: 12, right: 8, bottom: 22, left: 30 };

function niceMax(value: number): number {
    if (value <= 5) {
        return 5;
    }

    const magnitude = 10 ** Math.floor(Math.log10(value));
    const scaled = value / magnitude;
    const step = scaled <= 2 ? 2 : scaled <= 5 ? 5 : 10;

    return step * magnitude;
}

export function ActivityChart({ buckets }: { buckets: ActivityBucket[] }) {
    if (buckets.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No log activity recorded in the last 24 hours.
            </p>
        );
    }

    const innerW = PLOT.w - PLOT.left - PLOT.right;
    const innerH = PLOT.h - PLOT.top - PLOT.bottom;

    const totals = buckets.map((b) => b.total);
    const grandTotal = totals.reduce((a, b) => a + b, 0);
    const errorTotal = buckets.reduce((a, b) => a + b.error, 0);
    const warningTotal = buckets.reduce((a, b) => a + b.warning, 0);
    const max = niceMax(Math.max(1, ...totals));

    const peakIndex = totals.indexOf(Math.max(...totals));
    const gap = 3;
    const bandW = innerW / buckets.length;
    const barW = Math.max(2, bandW - gap);

    const y = (v: number) => PLOT.top + innerH - (v / max) * innerH;
    const ticks = [0, max / 2, max];

    const segments: { key: 'info' | 'warning' | 'error'; cls: string }[] = [
        { key: 'info', cls: 'fill-muted-foreground/35' },
        { key: 'warning', cls: 'fill-amber-500' },
        { key: 'error', cls: 'fill-red-500' },
    ];

    const label = (iso: string) =>
        new Date(iso).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });

    return (
        <figure className="space-y-3">
            <figcaption className="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                <span className="font-mono text-2xl font-semibold tabular-nums">
                    {grandTotal.toLocaleString()}
                </span>
                <span className="text-sm text-muted-foreground">
                    log events in the last 24 hours
                </span>
                <span className="ml-auto flex gap-3 font-mono text-xs text-muted-foreground">
                    <span className="text-red-600 dark:text-red-400">
                        {errorTotal} error{errorTotal === 1 ? '' : 's'}
                    </span>
                    <span className="text-amber-600 dark:text-amber-400">
                        {warningTotal} warning{warningTotal === 1 ? '' : 's'}
                    </span>
                </span>
            </figcaption>

            <svg
                viewBox={`0 0 ${PLOT.w} ${PLOT.h}`}
                className="w-full"
                role="img"
                aria-label={`Log volume per hour over the last 24 hours. ${grandTotal} events total, ${errorTotal} errors.`}
            >
                {ticks.map((t) => (
                    <g key={t}>
                        <line
                            x1={PLOT.left}
                            x2={PLOT.w - PLOT.right}
                            y1={y(t)}
                            y2={y(t)}
                            className="stroke-border"
                            strokeWidth={1}
                            strokeDasharray={t === 0 ? undefined : '2 3'}
                        />
                        <text
                            x={PLOT.left - 6}
                            y={y(t) + 3}
                            textAnchor="end"
                            className="fill-muted-foreground font-mono text-[9px]"
                        >
                            {Math.round(t)}
                        </text>
                    </g>
                ))}

                {buckets.map((bucket, i) => {
                    const x = PLOT.left + i * bandW + gap / 2;
                    let cursor = 0;

                    return (
                        <g key={bucket.start}>
                            <title>
                                {`${label(bucket.start)} — ${bucket.total} event${
                                    bucket.total === 1 ? '' : 's'
                                }` +
                                    (bucket.error
                                        ? `, ${bucket.error} error${bucket.error === 1 ? '' : 's'}`
                                        : '') +
                                    (bucket.warning
                                        ? `, ${bucket.warning} warning${bucket.warning === 1 ? '' : 's'}`
                                        : '')}
                            </title>
                            {bucket.total === 0 && (
                                <rect
                                    x={x}
                                    y={PLOT.top + innerH - 1}
                                    width={barW}
                                    height={1}
                                    className="fill-border"
                                />
                            )}
                            {segments.map((seg) => {
                                const value = bucket[seg.key];

                                if (value === 0) {
                                    return null;
                                }

                                const h = (value / max) * innerH;
                                cursor += h;

                                return (
                                    <rect
                                        key={seg.key}
                                        x={x}
                                        y={PLOT.top + innerH - cursor}
                                        width={barW}
                                        height={h}
                                        className={seg.cls}
                                    />
                                );
                            })}
                        </g>
                    );
                })}

                {peakIndex >= 0 && totals[peakIndex] > 0 && (
                    <text
                        x={PLOT.left + peakIndex * bandW + bandW / 2}
                        y={y(totals[peakIndex]) - 4}
                        textAnchor="middle"
                        className="fill-foreground font-mono text-[9px] font-semibold"
                    >
                        {totals[peakIndex]}
                    </text>
                )}

                {[0, Math.floor(buckets.length / 2), buckets.length - 1].map(
                    (i) => (
                        <text
                            key={i}
                            x={Math.min(
                                PLOT.w - PLOT.right,
                                Math.max(
                                    PLOT.left,
                                    PLOT.left + i * bandW + bandW / 2,
                                ),
                            )}
                            y={PLOT.h - 6}
                            textAnchor={
                                i === 0
                                    ? 'start'
                                    : i === buckets.length - 1
                                      ? 'end'
                                      : 'middle'
                            }
                            className="fill-muted-foreground font-mono text-[9px]"
                        >
                            {i === buckets.length - 1
                                ? 'now'
                                : label(buckets[i].start)}
                        </text>
                    ),
                )}
            </svg>
        </figure>
    );
}

/* -------------------------------------------------------------------------- */
/*  System topology — the live connection graph.                               */
/* -------------------------------------------------------------------------- */

const edgeStroke: Record<Tone, string> = {
    ok: 'stroke-emerald-500',
    warn: 'stroke-amber-500',
    bad: 'stroke-red-500',
    muted: 'stroke-border',
};

const nodeDot: Record<Tone, string> = {
    ok: 'fill-emerald-500',
    warn: 'fill-amber-500',
    bad: 'fill-red-500',
    muted: 'fill-muted-foreground',
};

function probeTone(status: string): Tone {
    return status === 'ok' ? 'ok' : status === 'error' ? 'bad' : 'warn';
}

type Node = {
    id: string;
    x: number;
    y: number;
    label: string;
    metric: string;
    tone: Tone;
};

function Edge({
    from,
    to,
    tone,
    label,
}: {
    from: Node;
    to: Node;
    tone: Tone;
    label: string;
}) {
    const mx = (from.x + to.x) / 2;
    const my = (from.y + to.y) / 2;
    const healthy = tone === 'ok';

    return (
        <g>
            <line
                x1={from.x}
                y1={from.y}
                x2={to.x}
                y2={to.y}
                className={cn(edgeStroke[tone], !healthy && 'opacity-90')}
                strokeWidth={healthy ? 1.5 : 2}
                strokeDasharray={healthy ? undefined : '5 4'}
                strokeLinecap="round"
            />
            {healthy && (
                <circle
                    r={3}
                    cx={mx}
                    cy={my}
                    className={cn(
                        nodeDot[tone],
                        'animate-ping motion-reduce:hidden',
                    )}
                />
            )}
            <g transform={`translate(${mx}, ${my})`}>
                <rect
                    x={-label.length * 3.1 - 4}
                    y={-8}
                    width={label.length * 6.2 + 8}
                    height={16}
                    rx={4}
                    className="fill-background stroke-border"
                    strokeWidth={1}
                />
                <text
                    textAnchor="middle"
                    y={3}
                    className="fill-muted-foreground font-mono text-[9px]"
                >
                    {label}
                </text>
            </g>
        </g>
    );
}

function NodeBox({ node, wide = 118 }: { node: Node; wide?: number }) {
    const h = 46;

    return (
        <g transform={`translate(${node.x - wide / 2}, ${node.y - h / 2})`}>
            <rect
                width={wide}
                height={h}
                rx={8}
                className="fill-card stroke-border"
                strokeWidth={1}
            />
            <circle cx={14} cy={16} r={3.5} className={nodeDot[node.tone]} />
            <text
                x={26}
                y={19}
                className="fill-foreground text-[11px] font-medium"
            >
                {node.label}
            </text>
            <text
                x={14}
                y={34}
                className="fill-muted-foreground font-mono text-[9px]"
            >
                {node.metric}
            </text>
        </g>
    );
}

function workerPhrase(status: string): string {
    return status === 'ok'
        ? 'running'
        : status === 'stale'
          ? 'not responding'
          : status === 'error'
            ? 'stopped'
            : 'unknown';
}

export function SystemTopology({ health }: { health: HealthSnapshot }) {
    const app: Node = {
        id: 'app',
        x: 300,
        y: 150,
        label: 'HRMS app',
        metric: `${health.environment} · Laravel ${health.laravel_version}`,
        tone: 'ok',
    };

    const database: Node = {
        id: 'db',
        x: 108,
        y: 55,
        label: 'Database',
        metric:
            health.database.latency_ms !== undefined
                ? `${health.database.latency_ms} ms`
                : health.database.status,
        tone: probeTone(health.database.status),
    };

    const storage: Node = {
        id: 'storage',
        x: 108,
        y: 245,
        label: 'File storage',
        metric: health.local_storage.disk ?? health.local_storage.status,
        tone: probeTone(health.local_storage.status),
    };

    const scheduler: Node = {
        id: 'scheduler',
        x: 300,
        y: 262,
        label: 'Scheduler',
        metric:
            health.scheduler.minutes_ago !== undefined
                ? `beat ${health.scheduler.minutes_ago}m ago`
                : 'no heartbeat',
        tone: probeTone(health.scheduler.status),
    };

    const queue: Node = {
        id: 'queue',
        x: 555,
        y: 90,
        label: 'Job queue',
        metric: `${health.queue.pending_jobs} queued · ${health.queue.failed_jobs} failed`,
        tone:
            health.queue.failed_jobs > 0
                ? 'bad'
                : health.queue.pending_jobs > 0 &&
                    health.queue.worker.status !== 'ok'
                  ? 'warn'
                  : 'ok',
    };

    const worker: Node = {
        id: 'worker',
        x: 555,
        y: 215,
        label: 'Queue worker',
        metric: workerPhrase(health.queue.worker.status),
        tone: probeTone(health.queue.worker.status),
    };

    return (
        <svg
            viewBox="0 0 720 300"
            className="w-full"
            role="img"
            aria-label={`System topology. Database ${database.metric}, file storage ${storage.tone}, scheduler ${scheduler.tone}, queue worker ${worker.metric}.`}
        >
            <Edge
                from={app}
                to={database}
                tone={database.tone}
                label={database.metric}
            />
            <Edge
                from={app}
                to={storage}
                tone={storage.tone}
                label={storage.metric}
            />
            <Edge
                from={scheduler}
                to={app}
                tone={scheduler.tone}
                label={scheduler.metric}
            />
            <Edge
                from={app}
                to={queue}
                tone={queue.tone}
                label={`${health.queue.pending_jobs} queued`}
            />
            <Edge
                from={queue}
                to={worker}
                tone={worker.tone}
                label={`worker ${worker.metric}`}
            />

            <NodeBox node={database} />
            <NodeBox node={storage} />
            <NodeBox node={scheduler} />
            <NodeBox node={queue} />
            <NodeBox node={worker} />
            <NodeBox node={app} wide={150} />
        </svg>
    );
}
