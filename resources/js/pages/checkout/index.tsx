import { Button } from '@/components/ui/button';
import { Container } from '@/components/ui/container';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type ProductType } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';

interface CheckoutItem {
    id: number;
    title: string;
    type: ProductType;
    thumbnail: string | null;
    quantity: number;
    unit_price: number;
    line_total: number;
}

interface CheckoutIndexProps {
    items: CheckoutItem[];
    subtotal: number;
    currency: string;
    buyer: { name: string; email: string };
}

function money(value: number, currency = 'USD') {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
    } catch {
        return `$${value.toFixed(2)}`;
    }
}

export default function CheckoutIndex({ items, subtotal, currency, buyer }: CheckoutIndexProps) {
    const { t } = useTranslate();

    const form = useForm({
        billing_name: buyer.name ?? '',
        billing_email: buyer.email ?? '',
        billing_country: '',
        coupon_code: '',
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('checkout.store'), { preserveScroll: true });
    };

    return (
        <StorefrontLayout>
            <Head title={t('checkout.title')} />

            <Container className="py-10 sm:py-16">
                <div className="mb-8">
                    <Link
                        href={route('cart.show')}
                        className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        {t('checkout.back_to_cart')}
                    </Link>
                    <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                        {t('checkout.title')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">{t('checkout.subtitle')}</p>
                </div>

                <div className="grid gap-8 lg:grid-cols-[1fr_360px]">
                    {/* Billing form */}
                    <form onSubmit={submit} className="space-y-6">
                        <div className="rounded-xl border bg-card p-6 shadow-sm">
                            <h2 className="mb-4 font-display text-base font-semibold tracking-tight">
                                {t('checkout.billing_details')}
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="sm:col-span-2">
                                    <Label htmlFor="billing_name">{t('checkout.full_name')}</Label>
                                    <Input
                                        id="billing_name"
                                        value={form.data.billing_name}
                                        onChange={(e) => form.setData('billing_name', e.target.value)}
                                        required
                                    />
                                    {form.errors.billing_name && (
                                        <p className="mt-1 text-xs text-destructive">{form.errors.billing_name}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="billing_email">{t('checkout.email')}</Label>
                                    <Input
                                        id="billing_email"
                                        type="email"
                                        value={form.data.billing_email}
                                        onChange={(e) => form.setData('billing_email', e.target.value)}
                                        required
                                    />
                                    {form.errors.billing_email && (
                                        <p className="mt-1 text-xs text-destructive">{form.errors.billing_email}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="billing_country">{t('checkout.country')}</Label>
                                    <Input
                                        id="billing_country"
                                        maxLength={2}
                                        placeholder="US"
                                        value={form.data.billing_country}
                                        onChange={(e) => form.setData('billing_country', e.target.value.toUpperCase())}
                                    />
                                    {form.errors.billing_country && (
                                        <p className="mt-1 text-xs text-destructive">{form.errors.billing_country}</p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="rounded-xl border bg-card p-6 shadow-sm">
                            <Label htmlFor="coupon_code">{t('checkout.coupon')}</Label>
                            <Input
                                id="coupon_code"
                                placeholder={t('checkout.coupon_placeholder')}
                                value={form.data.coupon_code}
                                onChange={(e) => form.setData('coupon_code', e.target.value)}
                            />
                            {form.errors.coupon_code && (
                                <p className="mt-1 text-xs text-destructive">{form.errors.coupon_code}</p>
                            )}
                        </div>
                    </form>

                    {/* Order summary */}
                    <aside className="space-y-4">
                        <div className="sticky top-24 space-y-4 rounded-xl border bg-card p-6 shadow-sm">
                            <h2 className="font-display text-base font-semibold tracking-tight">
                                {t('checkout.order_summary')}
                            </h2>
                            <ul className="space-y-3 text-sm">
                                {items.map((item) => (
                                    <li key={item.id} className="flex justify-between gap-3">
                                        <span className="min-w-0">
                                            <span className="block truncate font-medium">{item.title}</span>
                                            <span className="text-xs text-muted-foreground">
                                                {t('checkout.qty')}: {item.quantity}
                                            </span>
                                        </span>
                                        <span className="tabular-nums">{money(item.line_total, currency)}</span>
                                    </li>
                                ))}
                            </ul>
                            <div className="flex items-baseline justify-between border-t pt-4">
                                <span className="font-display text-sm font-semibold">{t('checkout.subtotal')}</span>
                                <span className="font-display text-2xl font-semibold tabular-nums">
                                    {money(subtotal, currency)}
                                </span>
                            </div>
                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                disabled={form.processing}
                                onClick={submit}
                            >
                                {form.processing ? t('checkout.placing') : t('checkout.place_order')}
                            </Button>
                            <p className="flex items-center justify-center gap-1.5 text-center text-[11px] text-muted-foreground">
                                <ShieldCheck className="size-3" />
                                {t('checkout.secure_note')}
                            </p>
                        </div>
                    </aside>
                </div>
            </Container>
        </StorefrontLayout>
    );
}
