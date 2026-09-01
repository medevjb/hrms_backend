import type { PropsWithChildren } from 'react';

// The console's section navigation lives in the app sidebar (AppSidebar);
// this layout gives every /system page the same padding and content width so
// Overview, Logs, Queue, Schedule, and Audit line up.
export default function SystemLayout({ children }: PropsWithChildren) {
    return (
        <div className="mx-auto w-full max-w-6xl px-4 py-6">{children}</div>
    );
}
