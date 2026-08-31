import type { PropsWithChildren } from 'react';

// The console's section navigation lives in the app sidebar (AppSidebar);
// this layout just gives every /system page consistent padding.
export default function SystemLayout({ children }: PropsWithChildren) {
    return <div className="px-4 py-6">{children}</div>;
}
