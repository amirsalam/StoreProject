import { DashboardGrid, type WidgetPayload } from '@/components/widgets';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface PageProps {
    user: { id: number; name: string; role: string };
    widgets: WidgetPayload[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Team', href: '/dashboard' },
];

export default function TeamDashboard({ user, widgets }: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Team dashboard" />
            <DashboardGrid
                user={user}
                widgets={widgets}
                intro="Your assigned work and recent activity."
            />
        </AppLayout>
    );
}
