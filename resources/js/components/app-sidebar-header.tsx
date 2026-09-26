import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarToggle } from '@/components/sidebar-toggle';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center gap-2 border-b px-6 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarToggle className="-ms-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
        </header>
    );
}
