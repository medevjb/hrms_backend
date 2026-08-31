import { Link } from '@inertiajs/react';
import {
    Activity,
    ClipboardList,
    ListTree,
    ScrollText,
    ShieldCheck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { audit, logs, queue, schedule } from '@/routes/system';
import type { NavItem } from '@/types';

const consoleNavItems: NavItem[] = [
    { title: 'Overview', href: dashboard(), icon: Activity },
    { title: 'Logs', href: logs(), icon: ScrollText },
    { title: 'Queue', href: queue(), icon: ListTree },
    { title: 'Schedule', href: schedule(), icon: ClipboardList },
    { title: 'Audit', href: audit(), icon: ShieldCheck },
];

export function AppSidebar() {
    const { currentUrl } = useCurrentUrl();
    const overviewPath = toUrl(dashboard());

    const items = consoleNavItems.map((item) => {
        const path = toUrl(item.href);
        const isActive =
            path === overviewPath
                ? currentUrl === overviewPath
                : currentUrl.startsWith(path);

        return { ...item, isActive };
    });

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} label="Console" />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
