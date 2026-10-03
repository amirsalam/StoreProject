import { type StripeWebhookInfo } from '@/components/stripe-webhook-steps';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type PaymentProvider } from '@/types';
import { Head } from '@inertiajs/react';
import GatewayForm from './gateway-form';
import { useTranslate } from '@/hooks/use-translate';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/payment-gateways' },
    { title: 'Payment Gateways', href: '/admin/payment-gateways' },
    { title: 'New', href: '/admin/payment-gateways/create' },
];

export default function CreatePaymentGateway({ providers, stripeWebhook }: { providers: PaymentProvider[]; stripeWebhook: StripeWebhookInfo }) {
    const { __ } = useTranslate();
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Admin · New payment gateway')} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{__('New payment gateway')}</h1>
                    <p className="text-muted-foreground text-sm">{__('Configure a payment provider. Secret credentials are encrypted at rest.')}</p>
                </div>

                <GatewayForm providers={providers} stripeWebhook={stripeWebhook} />
            </div>
        </AppLayout>
    );
}
