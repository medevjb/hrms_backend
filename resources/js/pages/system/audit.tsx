import { Head, router, usePage } from '@inertiajs/react';
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
import { audit as auditRoute } from '@/routes/system';
import type { AuditEntry, AuditFilters, Paginated } from '@/types/system';
import { formatDateTime } from './parts';

type PageProps = {
    filters: AuditFilters;
    actions: string[];
    logs: Paginated<AuditEntry>;
};

const ALL_ACTIONS = '__all__';

export default function Audit() {
    const { filters, actions, logs } = usePage<PageProps>().props;
    const [entityType, setEntityType] = useState(filters.entity_type ?? '');

    const apply = (next: Partial<AuditFilters & { page: number }>) => {
        const merged = {
            action: filters.action,
            entity_type: entityType || null,
            user_id: filters.user_id,
            date_from: filters.date_from,
            date_to: filters.date_to,
            ...next,
        };
        router.get(auditRoute().url, clean(merged), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Audit log" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Audit log"
                    description="Read-only record of sensitive actions"
                />

                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply({});
                    }}
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="action">Action</Label>
                        <Select
                            value={filters.action ?? ALL_ACTIONS}
                            onValueChange={(value) =>
                                apply({
                                    action:
                                        value === ALL_ACTIONS ? null : value,
                                })
                            }
                        >
                            <SelectTrigger id="action" className="w-56">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL_ACTIONS}>
                                    All actions
                                </SelectItem>
                                {actions.map((action) => (
                                    <SelectItem key={action} value={action}>
                                        {action}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="entity">Entity type</Label>
                        <Input
                            id="entity"
                            value={entityType}
                            onChange={(e) => setEntityType(e.target.value)}
                            placeholder="Employee"
                            className="w-40"
                        />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="date"
                            defaultValue={filters.date_from ?? ''}
                            onChange={(e) =>
                                apply({ date_from: e.target.value || null })
                            }
                        />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            type="date"
                            defaultValue={filters.date_to ?? ''}
                            onChange={(e) =>
                                apply({ date_to: e.target.value || null })
                            }
                        />
                    </div>

                    <Button type="submit">Filter</Button>
                </form>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-3 py-2 font-medium">When</th>
                                <th className="px-3 py-2 font-medium">Actor</th>
                                <th className="px-3 py-2 font-medium">Action</th>
                                <th className="px-3 py-2 font-medium">Entity</th>
                                <th className="px-3 py-2 font-medium">IP</th>
                                <th className="px-3 py-2 font-medium">Detail</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {logs.data.map((entry) => (
                                <tr key={entry.id} className="align-top">
                                    <td className="px-3 py-2 text-xs whitespace-nowrap text-muted-foreground">
                                        {formatDateTime(entry.created_at)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {entry.user?.name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {entry.action}
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        {entry.entity_type
                                            ? `${entry.entity_type}${entry.entity_id ? ` #${entry.entity_id}` : ''}`
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {entry.ip_address ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        {entry.reason && (
                                            <p>{entry.reason}</p>
                                        )}
                                        {(entry.old_data || entry.new_data) && (
                                            <Collapsible>
                                                <CollapsibleTrigger asChild>
                                                    <Button
                                                        variant="link"
                                                        size="sm"
                                                        className="h-auto p-0 text-xs"
                                                    >
                                                        changes
                                                    </Button>
                                                </CollapsibleTrigger>
                                                <CollapsibleContent>
                                                    <pre className="mt-1 max-h-64 overflow-auto rounded bg-muted p-2">
                                                        {JSON.stringify(
                                                            {
                                                                old: entry.old_data,
                                                                new: entry.new_data,
                                                            },
                                                            null,
                                                            2,
                                                        )}
                                                    </pre>
                                                </CollapsibleContent>
                                            </Collapsible>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {logs.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-6 text-center text-sm text-muted-foreground"
                                    >
                                        No audit entries match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {logs.meta.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={logs.meta.current_page <= 1}
                            onClick={() =>
                                apply({ page: logs.meta.current_page - 1 })
                            }
                        >
                            Previous
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            page {logs.meta.current_page} of{' '}
                            {logs.meta.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={
                                logs.meta.current_page >= logs.meta.last_page
                            }
                            onClick={() =>
                                apply({ page: logs.meta.current_page + 1 })
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

function clean(
    filters: Partial<AuditFilters & { page: number }>,
): Record<string, string | number> {
    const params: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value !== null && value !== undefined && value !== '') {
            params[key] = value as string | number;
        }
    }

    return params;
}

Audit.layout = {
    breadcrumbs: [{ title: 'Audit log', href: auditRoute() }],
};
