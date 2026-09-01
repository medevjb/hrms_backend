import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { TopError } from '@/types/system';

export type Tone = 'ok' | 'warn' | 'bad' | 'muted';

const toneClass: Record<Tone, string> = {
    ok: 'text-emerald-600 dark:text-emerald-400',
    warn: 'text-amber-600 dark:text-amber-400',
    bad: 'text-red-600 dark:text-red-400',
    muted: 'text-muted-foreground',
};

const dotClass: Record<Tone, string> = {
    ok: 'bg-emerald-500',
    warn: 'bg-amber-500',
    bad: 'bg-red-500',
    muted: 'bg-muted-foreground/40',
};

/** PSR-3 severities by weight — mirrors App\Support\Logs\LogLevel. */
const LEVEL_WEIGHT: Record<string, number> = {
    DEBUG: 100,
    INFO: 200,
    NOTICE: 250,
    WARNING: 300,
    ERROR: 400,
    CRITICAL: 500,
    ALERT: 550,
    EMERGENCY: 600,
    RAW: 400,
};

function levelFill(level: string): string {
    const weight = LEVEL_WEIGHT[level.toUpperCase()] ?? 400;

    if (weight >= 500) {
        return 'bg-red-600';
    }

    if (weight >= 400) {
        return 'bg-red-500';
    }

    if (weight >= 300) {
        return 'bg-amber-500';
    }

    return 'bg-muted-foreground/50';
}

export function statusTone(status: string): Tone {
    switch (status) {
        case 'ok':
        case 'succeeded':
            return 'ok';
        case 'stale':
        case 'skipped':
        case 'unknown':
        case 'running':
            return 'warn';
        case 'error':
        case 'failed':
            return 'bad';
        default:
            return 'muted';
    }
}

export function StatusDot({ status }: { status: string }) {
    const tone = statusTone(status);

    return (
        <span className="inline-flex items-center gap-1.5">
            <span className={cn('size-2 rounded-full', dotClass[tone])} />
            <span className={cn('text-xs font-medium', toneClass[tone])}>
                {status}
            </span>
        </span>
    );
}

export function Tile({
    label,
    value,
    hint,
    tone = 'muted',
}: {
    label: string;
    value: React.ReactNode;
    hint?: React.ReactNode;
    tone?: Tone;
}) {
    return (
        <Card>
            <CardContent className="space-y-1 p-4">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                <p className={cn('text-lg font-semibold', toneClass[tone])}>
                    {value}
                </p>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
            </CardContent>
        </Card>
    );
}

const levelVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    EMERGENCY: 'destructive',
    ALERT: 'destructive',
    CRITICAL: 'destructive',
    ERROR: 'destructive',
    WARNING: 'secondary',
    NOTICE: 'secondary',
    INFO: 'outline',
    DEBUG: 'outline',
    RAW: 'outline',
};

export function LevelBadge({ level }: { level: string }) {
    return (
        <Badge variant={levelVariant[level] ?? 'outline'} className="font-mono">
            {level}
        </Badge>
    );
}

export function formatAge(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    if (seconds < 60) {
        return `${seconds}s`;
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)}m`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)}h`;
    }

    return `${Math.floor(seconds / 86400)}d`;
}

export function formatDuration(ms: number | null): string {
    if (ms === null) {
        return '—';
    }

    if (ms < 1000) {
        return `${ms}ms`;
    }

    return `${(ms / 1000).toFixed(1)}s`;
}

export function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

export function formatClock(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

/* -------------------------------------------------------------------------- */
/*  Console instrument panel — shared by the Overview and Logs pages.          */
/* -------------------------------------------------------------------------- */

const bandWash: Record<'ok' | 'warn' | 'bad', string> = {
    ok: 'border-emerald-500/25 bg-emerald-500/[0.07]',
    warn: 'border-amber-500/30 bg-amber-500/[0.08]',
    bad: 'border-red-500/30 bg-red-500/[0.08]',
};

export type Indicator = {
    label: string;
    value: string;
    tone: Tone;
};

/**
 * The one framed element on the console: a single-sentence verdict, a live
 * pulse, and a monospace strip of the numbers behind it.
 */
export function StatusBand({
    tone,
    verdict,
    detail,
    meta,
    indicators,
}: {
    tone: 'ok' | 'warn' | 'bad';
    verdict: string;
    detail?: string;
    meta?: string;
    indicators: Indicator[];
}) {
    return (
        <section className={cn('rounded-xl border', bandWash[tone])}>
            <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-2 p-5">
                <div className="flex items-start gap-3">
                    <span className="relative mt-1.5 flex size-2.5">
                        <span
                            className={cn(
                                'absolute inline-flex h-full w-full animate-ping rounded-full opacity-60 motion-reduce:hidden',
                                dotClass[tone],
                            )}
                        />
                        <span
                            className={cn(
                                'relative inline-flex size-2.5 rounded-full',
                                dotClass[tone],
                            )}
                        />
                    </span>
                    <div>
                        <p className="text-lg font-semibold tracking-tight">
                            {verdict}
                        </p>
                        {detail && (
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {detail}
                            </p>
                        )}
                    </div>
                </div>
                {meta && (
                    <p className="font-mono text-xs text-muted-foreground">
                        {meta}
                    </p>
                )}
            </div>
            <div className="flex flex-wrap gap-x-6 gap-y-1.5 border-t border-black/5 px-5 py-3 font-mono text-xs dark:border-white/10">
                {indicators.map((indicator) => (
                    <span
                        key={indicator.label}
                        className="inline-flex items-center gap-1.5"
                    >
                        <span
                            className={cn(
                                'size-1.5 rounded-full',
                                dotClass[indicator.tone],
                            )}
                        />
                        <span className="text-muted-foreground">
                            {indicator.label}
                        </span>
                        <span className="font-medium text-foreground">
                            {indicator.value}
                        </span>
                    </span>
                ))}
            </div>
        </section>
    );
}

/** A labelled section with an uppercase eyebrow and optional right-aligned aside. */
export function Panel({
    title,
    aside,
    children,
}: {
    title: string;
    aside?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="space-y-3">
            <div className="flex items-center justify-between gap-4">
                <h3 className="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                    {title}
                </h3>
                {aside}
            </div>
            {children}
        </section>
    );
}

/** One hairline row in a definition list: quiet label, monospace value. */
export function Field({
    label,
    value,
    tone = 'muted',
}: {
    label: string;
    value: ReactNode;
    tone?: Tone;
}) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-b border-border/60 py-2 last:border-0">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span
                className={cn(
                    'font-mono text-sm tabular-nums',
                    tone === 'muted' ? 'text-foreground' : toneClass[tone],
                )}
            >
                {value}
            </span>
        </div>
    );
}

/** A single measured value with a one-line interpretation beneath it. */
export function Readout({
    value,
    label,
    note,
    tone = 'muted',
}: {
    value: ReactNode;
    label: string;
    note: string;
    tone?: Tone;
}) {
    return (
        <div className="border-b border-border/60 py-3 last:border-0">
            <div className="flex items-baseline gap-2">
                <span
                    className={cn(
                        'font-mono text-xl font-semibold tabular-nums',
                        tone === 'muted' ? 'text-foreground' : toneClass[tone],
                    )}
                >
                    {value}
                </span>
                <span className="text-[11px] tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
            </div>
            <p className="mt-0.5 text-xs text-muted-foreground">{note}</p>
        </div>
    );
}

/** One service in the health checklist: dot, name, plain-English state. */
export function ServiceRow({
    name,
    tone,
    summary,
    detail,
    metric,
}: {
    name: string;
    tone: Tone;
    summary: string;
    detail?: string;
    metric?: string;
}) {
    return (
        <div className="flex items-start gap-3 border-b border-border/60 py-3 last:border-0">
            <span
                className={cn(
                    'mt-1.5 size-2 shrink-0 rounded-full',
                    dotClass[tone],
                )}
            />
            <div className="min-w-0 flex-1">
                <div className="flex items-baseline justify-between gap-3">
                    <span className="text-sm font-medium">{name}</span>
                    {metric && (
                        <span className="shrink-0 font-mono text-xs text-muted-foreground tabular-nums">
                            {metric}
                        </span>
                    )}
                </div>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {summary}
                </p>
                {detail && (
                    <p className="mt-1 font-mono text-xs break-words text-muted-foreground/80">
                        {detail}
                    </p>
                )}
            </div>
        </div>
    );
}

/** Distribution of grouped errors by severity — the analytical read. */
function SeverityBar({ errors }: { errors: TopError[] }) {
    const total = errors.reduce((sum, error) => sum + error.count, 0);

    if (total === 0) {
        return null;
    }

    const byLevel = new Map<string, number>();

    for (const error of errors) {
        byLevel.set(error.level, (byLevel.get(error.level) ?? 0) + error.count);
    }

    const segments = [...byLevel.entries()]
        .sort((a, b) => (LEVEL_WEIGHT[b[0]] ?? 0) - (LEVEL_WEIGHT[a[0]] ?? 0))
        .map(([level, count]) => ({ level, count }));

    return (
        <div className="space-y-2">
            <div className="flex h-2 w-full overflow-hidden rounded-full bg-muted">
                {segments.map((segment) => (
                    <div
                        key={segment.level}
                        className={cn('h-full', levelFill(segment.level))}
                        style={{ width: `${(segment.count / total) * 100}%` }}
                    />
                ))}
            </div>
            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                {segments.map((segment) => (
                    <span
                        key={segment.level}
                        className="inline-flex items-center gap-1.5"
                    >
                        <span
                            className={cn(
                                'size-1.5 rounded-full',
                                levelFill(segment.level),
                            )}
                        />
                        {segment.level.toLowerCase()}
                        <span className="font-mono text-foreground tabular-nums">
                            {segment.count}
                        </span>
                    </span>
                ))}
            </div>
        </div>
    );
}

/**
 * Grouped errors, plain-English first: the explanation is the headline, the
 * raw message and severity sit beneath it, and a bar shows relative volume.
 */
export function TopErrorList({
    errors,
    emptyMessage = 'No errors logged in the last 24 hours. The app is running clean.',
}: {
    errors: TopError[];
    emptyMessage?: string;
}) {
    if (errors.length === 0) {
        return <p className="text-sm text-muted-foreground">{emptyMessage}</p>;
    }

    const max = Math.max(...errors.map((error) => error.count));

    return (
        <div className="space-y-4">
            <SeverityBar errors={errors} />

            <ol>
                {errors.map((error, index) => (
                    <li
                        key={error.message}
                        className="grid grid-cols-[1.75rem_1fr] gap-x-3 border-b border-border/60 py-3 last:border-0"
                    >
                        <span className="pt-0.5 font-mono text-xs text-muted-foreground tabular-nums">
                            {String(index + 1).padStart(2, '0')}
                        </span>
                        <div className="min-w-0">
                            <div className="flex items-baseline justify-between gap-3">
                                <p className="text-sm font-medium">
                                    {error.explanation}
                                </p>
                                <span className="shrink-0 font-mono text-sm font-semibold tabular-nums">
                                    {error.count}
                                    <span className="text-muted-foreground">
                                        ×
                                    </span>
                                </span>
                            </div>
                            <div className="mt-2 h-1 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className={cn(
                                        'h-full rounded-full',
                                        levelFill(error.level),
                                    )}
                                    style={{
                                        width: `${Math.max(4, (error.count / max) * 100)}%`,
                                    }}
                                />
                            </div>
                            <p
                                className="mt-2 truncate font-mono text-xs text-muted-foreground"
                                title={error.message}
                            >
                                {error.message}
                            </p>
                            <div className="mt-1.5 flex items-center gap-2">
                                <LevelBadge level={error.level} />
                                <span className="text-xs text-muted-foreground">
                                    last seen {formatDateTime(error.last_seen)}
                                </span>
                            </div>
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}

/** The severity tag on the left edge of a log row. */
export function LevelRail({ level }: { level: string }) {
    const weight = LEVEL_WEIGHT[level.toUpperCase()] ?? 0;

    const tone =
        weight >= 500
            ? 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400'
            : weight >= 400
              ? 'border-red-500/25 bg-red-500/[0.07] text-red-600 dark:text-red-400'
              : weight >= 300
                ? 'border-amber-500/25 bg-amber-500/[0.07] text-amber-600 dark:text-amber-400'
                : weight >= 200
                  ? 'border-border bg-muted/40 text-muted-foreground'
                  : 'border-border bg-transparent text-muted-foreground/70';

    return (
        <div
            className={cn(
                'flex w-14 shrink-0 items-start justify-center border-r py-3 font-mono text-[10px] font-semibold tracking-wider',
                tone,
            )}
        >
            {level.toUpperCase().slice(0, 3)}
        </div>
    );
}
