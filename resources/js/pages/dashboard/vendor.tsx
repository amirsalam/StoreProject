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
    { title: 'Vendor', href: '/dashboard' },
];

export default function VendorDashboard({ user, widgets }: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vendor dashboard" />
            <DashboardGrid
                user={user}
                widgets={widgets}
                intro="Revenue, orders, and the products driving them."
            />
        </AppLayout>
    );
}
