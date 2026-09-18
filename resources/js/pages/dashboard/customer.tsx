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
];

export default function CustomerDashboard({ user, widgets }: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Your dashboard" />
            <DashboardGrid
                user={user}
                widgets={widgets}
                intro="Your orders, subscriptions, and wallet at a glance."
            />
        </AppLayout>
    );
}
