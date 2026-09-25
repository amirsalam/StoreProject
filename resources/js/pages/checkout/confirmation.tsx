import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Container } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type ProductType } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

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
}

function money(value: string, currency = 'USD') {
    const n = Number(value);
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(n);
    } catch {
        return `$${n.toFixed(2)}`;
    }
}

export default function CheckoutConfirmation({ order }: ConfirmationProps) {
    const { t } = useTranslate();
    const isPaid = order.status === 'paid';

    return (
        <StorefrontLayout>
            <Head title={t('checkout.confirm_title')} />

            <Container className="py-12 sm:py-20">
                <div className="mx-auto max-w-xl">
                    <div className="flex flex-col items-center text-center">
                        <span className="mb-4 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <CheckCircle2 className="size-6" />
                        </span>
                        <h1 className="font-display text-3xl font-semibold tracking-tight">
                            {t('checkout.confirm_title')}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">{t('checkout.confirm_thank_you')}</p>
                    </div>

                    <div className="mt-8 rounded-xl border bg-card p-6 shadow-sm">
                        <div className="flex items-center justify-between border-b pb-4">
                            <div>
                                <p className="text-xs text-muted-foreground">{t('checkout.order_number')}</p>
                                <p className="font-mono text-sm font-semibold">{order.order_number}</p>
                            </div>
                            <Badge variant={isPaid ? 'default' : 'secondary'}>{order.status}</Badge>
                        </div>

                        <ul className="space-y-3 py-4 text-sm">
                            {order.items.map((item, i) => (
                                <li key={i} className="flex justify-between gap-3">
                                    <span className="min-w-0">
                                        <span className="block truncate font-medium">{item.product_title}</span>
                                        <span className="text-xs text-muted-foreground">
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
                                    <dd className="tabular-nums text-primary">
                                        −{money(order.discount, order.currency)}
                                    </dd>
                                </div>
                            )}
                            <div className="flex items-baseline justify-between pt-1">
                                <dt className="font-display font-semibold">{t('checkout.total')}</dt>
                                <dd className="font-display text-xl font-semibold tabular-nums">
                                    {money(order.total, order.currency)}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <p className="mt-4 text-center text-sm text-muted-foreground">
                        {isPaid ? t('checkout.paid_note') : t('checkout.pending_note')}
                    </p>

                    <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <Button asChild>
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
