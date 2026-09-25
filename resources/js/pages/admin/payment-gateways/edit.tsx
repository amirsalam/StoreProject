import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type PaymentProvider } from '@/types';
import { Head } from '@inertiajs/react';
import GatewayForm, { type GatewayFormValues } from './gateway-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/payment-gateways' },
    { title: 'Payment Gateways', href: '/admin/payment-gateways' },
    { title: 'Edit', href: '/admin/payment-gateways' },
];

interface Props {
    gateway: GatewayFormValues;
    providers: PaymentProvider[];
}

export default function EditPaymentGateway({ gateway, providers }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Admin · Edit ${gateway.display_name}`} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Edit {gateway.display_name}</h1>
                    <p className="text-sm text-muted-foreground">
                        Update configuration. Leave secret fields blank to keep their stored values.
                    </p>
                </div>

                <GatewayForm gateway={gateway} providers={providers} />
            </div>
        </AppLayout>
    );
}
