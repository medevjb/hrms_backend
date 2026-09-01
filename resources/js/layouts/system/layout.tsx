import type { PropsWithChildren } from 'react';

// The console's section navigation lives in the app sidebar (AppSidebar);
// this layout gives every /system page the same padding and content width so
// Overview, Logs, Queue, Schedule, and Audit line up with each other — and
// with the main app's System settings (its "Leave" tab caps at max-w-5xl).
export default function SystemLayout({ children }: PropsWithChildren) {
    return <div className="w-full max-w-5xl px-4 py-6">{children}</div>;
}
