import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type PaymentProvider } from '@/types';
import { Head } from '@inertiajs/react';
import GatewayForm from './gateway-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/payment-gateways' },
    { title: 'Payment Gateways', href: '/admin/payment-gateways' },
    { title: 'New', href: '/admin/payment-gateways/create' },
];

export default function CreatePaymentGateway({ providers }: { providers: PaymentProvider[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin · New payment gateway" />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">New payment gateway</h1>
                    <p className="text-sm text-muted-foreground">
                        Configure a payment provider. Secret credentials are encrypted at rest.
                    </p>
                </div>

                <GatewayForm providers={providers} />
            </div>
        </AppLayout>
    );
}
