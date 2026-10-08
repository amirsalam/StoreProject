import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Container } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type ProductType } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Landmark } from 'lucide-react';

interface ConfirmationItem {
    product_title: string;
    product_type: ProductType;
    quantity: number;
    total_price: string;
}

interface ConfirmationProps {
    order: {
        order_number: string;
        status: string;
        subtotal: string;
        discount: string;
        total: string;
        currency: string;
        billing_email: string;
        paid_at: string | null;
        items: ConfirmationItem[];
    };
    /** Back from a declined/cancelled CMI payment. */
    paymentFailed?: boolean;
    /** CMI order still unpaid: send the buyer back to CMI to try again. */
    retryUrl?: string | null;
    /** Waiting for a bank transfer: where to send the money. */
    bankTransfer?: BankDetails | null;
}

interface BankDetails {
    account_name: string | null;
    bank_name: string | null;
    account_number: string | null;
    iban: string | null;
    swift: string | null;
    instructions: string | null;
}

function money(value: string, currency = 'USD') {
    const n = Number(value);
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(n);
    } catch {
        return `$${n.toFixed(2)}`;
    }
}

export default function CheckoutConfirmation({ order, paymentFailed = false, retryUrl = null, bankTransfer = null }: ConfirmationProps) {
    const { t } = useTranslate();
    const isPaid = order.status === 'paid';

    return (
        <StorefrontLayout>
            <Head title={t('checkout.confirm_title')} />

            <Container className="py-12 sm:py-20">
                <div className="mx-auto max-w-xl">
                    <div className="flex flex-col items-center text-center">
                        <span className="bg-primary/10 text-primary mb-4 flex size-12 items-center justify-center rounded-full">
                            <CheckCircle2 className="size-6" />
                        </span>
                        <h1 className="font-display text-3xl font-semibold tracking-tight">{t('checkout.confirm_title')}</h1>
                        <p className="text-muted-foreground mt-2 text-sm">{t('checkout.confirm_thank_you')}</p>
                    </div>

                    {paymentFailed && (
                        <div
                            role="alert"
                            className="mt-8 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
                        >
                            <p className="font-semibold">{t('checkout.payment_failed_title')}</p>
                            <p className="mt-1">{t('checkout.payment_failed_body')}</p>
                        </div>
                    )}

                    {bankTransfer && (
                        <div className="border-primary/40 bg-primary/[0.04] mt-8 rounded-xl border p-6 shadow-sm">
                            <h2 className="font-display flex items-center gap-2 text-base font-semibold tracking-tight">
                                <Landmark className="size-4" />
                                {t('checkout.bank_transfer_title')}
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {t('checkout.bank_transfer_body', { amount: money(order.total, order.currency) })}
                            </p>
                            <dl className="mt-4 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[auto_1fr]">
                                {(
                                    [
                                        ['bank_account_name', bankTransfer.account_name],
                                        ['bank_name', bankTransfer.bank_name],
                                        ['bank_account_number', bankTransfer.account_number],
                                        ['bank_iban', bankTransfer.iban],
                                        ['bank_swift', bankTransfer.swift],
                                    ] as const
                                )
                                    .filter(([, value]) => value)
                                    .map(([key, value]) => (
                                        <div key={key} className="contents">
                                            <dt className="text-muted-foreground">{t(`checkout.${key}`)}</dt>
                                            <dd className="font-mono font-medium break-all" dir="ltr">
                                                {value}
                                            </dd>
                                        </div>
                                    ))}
                                <dt className="text-muted-foreground">{t('checkout.bank_amount')}</dt>
                                <dd className="font-mono font-semibold" dir="ltr">
                                    {money(order.total, order.currency)}
                                </dd>
                                <dt className="text-muted-foreground">{t('checkout.bank_reference')}</dt>
                                <dd className="text-primary font-mono font-semibold" dir="ltr">
                                    {order.order_number}
                                </dd>
                            </dl>
                            {bankTransfer.instructions && (
                                <p className="text-muted-foreground mt-4 text-sm whitespace-pre-line">{bankTransfer.instructions}</p>
                            )}
                        </div>
                    )}

                    <div className="bg-card mt-8 rounded-xl border p-6 shadow-sm">
                        <div className="flex items-center justify-between border-b pb-4">
                            <div>
                                <p className="text-muted-foreground text-xs">{t('checkout.order_number')}</p>
                                <p className="font-mono text-sm font-semibold">{order.order_number}</p>
                            </div>
                            <Badge variant={isPaid ? 'default' : 'secondary'}>{order.status}</Badge>
                        </div>

                        <ul className="space-y-3 py-4 text-sm">
                            {order.items.map((item, i) => (
                                <li key={i} className="flex justify-between gap-3">
                                    <span className="min-w-0">
                                        <span className="block truncate font-medium">{item.product_title}</span>
                                        <span className="text-muted-foreground text-xs">
                                            {t('checkout.qty')}: {item.quantity}
                                        </span>
                                    </span>
                                    <span className="tabular-nums">{money(item.total_price, order.currency)}</span>
                                </li>
                            ))}
                        </ul>

                        <dl className="space-y-1.5 border-t pt-4 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">{t('checkout.subtotal')}</dt>
                                <dd className="tabular-nums">{money(order.subtotal, order.currency)}</dd>
                            </div>
                            {Number(order.discount) > 0 && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">{t('checkout.discount')}</dt>
                                    <dd className="text-primary tabular-nums">−{money(order.discount, order.currency)}</dd>
                                </div>
                            )}
                            <div className="flex items-baseline justify-between pt-1">
                                <dt className="font-display font-semibold">{t('checkout.total')}</dt>
                                <dd className="font-display text-xl font-semibold tabular-nums">{money(order.total, order.currency)}</dd>
                            </div>
                        </dl>
                    </div>

                    <p className="text-muted-foreground mt-4 text-center text-sm">
                        {isPaid ? t('checkout.paid_note') : bankTransfer ? t('checkout.bank_pending_note') : t('checkout.pending_note')}
                    </p>

                    <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        {retryUrl && (
                            // A plain link: retrying leaves this app (CMI's or PayPal's own site).
                            <Button asChild>
                                <a href={retryUrl}>{t('checkout.retry_payment')}</a>
                            </Button>
                        )}
                        <Button asChild variant={retryUrl ? 'outline' : 'default'}>
                            <Link href={route('dashboard')}>{t('checkout.view_dashboard')}</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={route('products.index')}>{t('checkout.continue_shopping')}</Link>
                        </Button>
                    </div>
                </div>
            </Container>
        </StorefrontLayout>
    );
}
