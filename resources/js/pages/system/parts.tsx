import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Tone = 'ok' | 'warn' | 'bad' | 'muted';

const toneClass: Record<Tone, string> = {
    ok: 'text-emerald-600 dark:text-emerald-400',
    warn: 'text-amber-600 dark:text-amber-400',
    bad: 'text-red-600 dark:text-red-400',
    muted: 'text-muted-foreground',
};

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
    const dot: Record<Tone, string> = {
        ok: 'bg-emerald-500',
        warn: 'bg-amber-500',
        bad: 'bg-red-500',
        muted: 'bg-muted-foreground/50',
    };

    return (
        <span className="inline-flex items-center gap-1.5">
            <span className={cn('size-2 rounded-full', dot[tone])} />
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
