import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { schedule as scheduleRoute } from '@/routes/system';
import { show as scheduleShow } from '@/routes/system/schedule';
import type { ScheduledCommand } from '@/types/system';
import { formatDateTime, formatDuration, StatusDot } from './parts';

type PageProps = {
    commands: ScheduledCommand[];
};

export default function Schedule() {
    const { commands } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Schedule" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Schedule"
                    description="Registered scheduled commands and their run history"
                />

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Command
                                </th>
                                <th className="px-3 py-2 font-medium">Cron</th>
                                <th className="px-3 py-2 font-medium">
                                    Next due
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Last run
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Failures (7d)
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {commands.map((command) => (
                                <tr key={command.command}>
                                    <td className="px-3 py-2">
                                        <Link
                                            href={
                                                scheduleShow(
                                                    command.command,
                                                ).url
                                            }
                                            className="font-mono font-medium hover:underline"
                                        >
                                            {command.command}
                                        </Link>
                                        {command.description && (
                                            <p className="text-xs text-muted-foreground">
                                                {command.description}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {command.expression}
                                    </td>
                                    <td className="px-3 py-2 text-xs text-muted-foreground">
                                        {formatDateTime(command.next_due_at)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {command.last_run ? (
                                            <span className="flex flex-col">
                                                <StatusDot
                                                    status={
                                                        command.last_run.status
                                                    }
                                                />
                                                <span className="text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        command.last_run
                                                            .started_at,
                                                    )}{' '}
                                                    ·{' '}
                                                    {formatDuration(
                                                        command.last_run
                                                            .duration_ms,
                                                    )}
                                                </span>
                                            </span>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                never
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {command.recent_failures > 0 ? (
                                            <span className="text-red-600 dark:text-red-400">
                                                {command.recent_failures}
                                            </span>
                                        ) : (
                                            '0'
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Schedule.layout = {
    breadcrumbs: [{ title: 'Schedule', href: scheduleRoute() }],
};
