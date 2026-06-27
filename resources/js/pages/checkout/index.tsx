import { Button } from '@/components/ui/button';
import { Container } from '@/components/ui/container';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type ProductType } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Elements, PaymentElement, useElements, useStripe } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { ArrowLeft, Lock, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';

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
    stripeKey: string | null;
}

interface BillingData {
    billing_name: string;
    billing_email: string;
    billing_country: string;
    coupon_code: string;
    notes: string;
}

function money(value: number, currency = 'USD') {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
    } catch {
        return `$${value.toFixed(2)}`;
    }
}

/** Read Laravel's XSRF-TOKEN cookie so a manual JSON POST passes CSRF. */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export default function CheckoutIndex({ items, subtotal, currency, buyer, stripeKey }: CheckoutIndexProps) {
    const { t } = useTranslate();

    // Load Stripe.js once, only when a publishable key is configured.
    const stripePromise = useMemo(() => (stripeKey ? loadStripe(stripeKey) : null), [stripeKey]);

    const [billing, setBilling] = useState<BillingData>({
        billing_name: buyer.name ?? '',
        billing_email: buyer.email ?? '',
        billing_country: '',
        coupon_code: '',
        notes: '',
    });
    const [errors, setErrors] = useState<Partial<Record<keyof BillingData | 'cart', string>>>({});
    const [placing, setPlacing] = useState(false);
    // Once the order + Stripe PaymentIntent exist, we move to the card phase.
    const [clientSecret, setClientSecret] = useState<string | null>(null);
    const [confirmationUrl, setConfirmationUrl] = useState<string>('');

    const set = (key: keyof BillingData, value: string) => setBilling((prev) => ({ ...prev, [key]: value }));

    const isDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');

    // Phase 1: create the order + PaymentIntent, then reveal the card form.
    const placeOrder = async (e: React.FormEvent) => {
        e.preventDefault();
        if (placing) return;
        setPlacing(true);
        setErrors({});

        try {
            const res = await fetch(route('checkout.store'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(billing),
            });

            const payload = await res.json().catch(() => ({}));

            if (!res.ok) {
                // 422 — surface coupon / validation errors; redirect on empty cart.
                if (payload.redirect) {
                    router.visit(payload.redirect);
                    return;
                }
                const flat: Record<string, string> = {};
                Object.entries(payload.errors ?? {}).forEach(([k, v]) => {
                    flat[k] = Array.isArray(v) ? String(v[0]) : String(v);
                });
                setErrors(flat);
                setPlacing(false);
                return;
            }

            setConfirmationUrl(payload.confirmation_url);

            // Fully-discounted ($0) order — already settled server-side, no card.
            if (!payload.client_secret) {
                router.visit(payload.confirmation_url);
                return;
            }

            setClientSecret(payload.client_secret);
            setPlacing(false);
        } catch {
            setErrors({ cart: t('checkout.payment_error') });
            setPlacing(false);
        }
    };

    const inPaymentPhase = clientSecret !== null;

    return (
        <StorefrontLayout>
            <Head title={t('checkout.title')} />

            <Container className="py-10 sm:py-16">
                <div className="mb-8">
                    <Link
                        href={route('cart.show')}
                        className="text-muted-foreground hover:text-foreground mb-4 inline-flex items-center gap-1.5 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        {t('checkout.back_to_cart')}
                    </Link>
                    <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">{t('checkout.title')}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">{t('checkout.subtitle')}</p>
                </div>

                <div className="grid gap-8 lg:grid-cols-[1fr_360px]">
                    <div className="space-y-6">
                        {/* Phase 1 — billing details */}
                        <form onSubmit={placeOrder} className="space-y-6">
                            <div className="bg-card rounded-xl border p-6 shadow-sm">
                                <h2 className="font-display mb-4 text-base font-semibold tracking-tight">{t('checkout.billing_details')}</h2>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="sm:col-span-2">
                                        <Label htmlFor="billing_name">{t('checkout.full_name')}</Label>
                                        <Input
                                            id="billing_name"
                                            value={billing.billing_name}
                                            onChange={(e) => set('billing_name', e.target.value)}
                                            disabled={inPaymentPhase}
                                            required
                                        />
                                        {errors.billing_name && <p className="text-destructive mt-1 text-xs">{errors.billing_name}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="billing_email">{t('checkout.email')}</Label>
                                        <Input
                                            id="billing_email"
                                            type="email"
                                            value={billing.billing_email}
                                            onChange={(e) => set('billing_email', e.target.value)}
                                            disabled={inPaymentPhase}
                                            required
                                        />
                                        {errors.billing_email && <p className="text-destructive mt-1 text-xs">{errors.billing_email}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="billing_country">{t('checkout.country')}</Label>
                                        <Input
                                            id="billing_country"
                                            maxLength={2}
                                            placeholder="US"
                                            value={billing.billing_country}
                                            onChange={(e) => set('billing_country', e.target.value.toUpperCase())}
                                            disabled={inPaymentPhase}
                                        />
                                        {errors.billing_country && <p className="text-destructive mt-1 text-xs">{errors.billing_country}</p>}
                                    </div>
                                </div>
                            </div>

                            {!inPaymentPhase && (
                                <div className="bg-card rounded-xl border p-6 shadow-sm">
                                    <Label htmlFor="coupon_code">{t('checkout.coupon')}</Label>
                                    <Input
                                        id="coupon_code"
                                        placeholder={t('checkout.coupon_placeholder')}
                                        value={billing.coupon_code}
                                        onChange={(e) => set('coupon_code', e.target.value)}
                                    />
                                    {errors.coupon_code && <p className="text-destructive mt-1 text-xs">{errors.coupon_code}</p>}
                                </div>
                            )}
                        </form>

                        {/* Phase 2 — card payment (Stripe Elements) */}
                        {inPaymentPhase && stripePromise && clientSecret && (
                            <div className="bg-card rounded-xl border p-6 shadow-sm">
                                <h2 className="font-display mb-4 flex items-center gap-2 text-base font-semibold tracking-tight">
                                    <Lock className="text-muted-foreground size-4" />
                                    {t('checkout.payment_details')}
                                </h2>
                                <Elements
                                    stripe={stripePromise}
                                    options={{
                                        clientSecret,
                                        appearance: { theme: isDark ? 'night' : 'stripe' },
                                    }}
                                >
                                    <PaymentStep confirmationUrl={confirmationUrl} amountLabel={money(subtotal, currency)} />
                                </Elements>
                            </div>
                        )}

                        {errors.cart && <p className="text-destructive text-sm">{errors.cart}</p>}
                    </div>

                    {/* Order summary */}
                    <aside className="space-y-4">
                        <div className="bg-card sticky top-24 space-y-4 rounded-xl border p-6 shadow-sm">
                            <h2 className="font-display text-base font-semibold tracking-tight">{t('checkout.order_summary')}</h2>
                            <ul className="space-y-3 text-sm">
                                {items.map((item) => (
                                    <li key={item.id} className="flex justify-between gap-3">
                                        <span className="min-w-0">
                                            <span className="block truncate font-medium">{item.title}</span>
                                            <span className="text-muted-foreground text-xs">
                                                {t('checkout.qty')}: {item.quantity}
                                            </span>
                                        </span>
                                        <span className="tabular-nums">{money(item.line_total, currency)}</span>
                                    </li>
                                ))}
                            </ul>
                            <div className="flex items-baseline justify-between border-t pt-4">
                                <span className="font-display text-sm font-semibold">{t('checkout.subtotal')}</span>
                                <span className="font-display text-2xl font-semibold tabular-nums">{money(subtotal, currency)}</span>
                            </div>

                            {!inPaymentPhase &&
                                (stripeKey ? (
                                    <Button type="button" size="lg" className="w-full" disabled={placing} onClick={placeOrder}>
                                        {placing ? t('checkout.placing') : t('checkout.continue_to_payment')}
                                    </Button>
                                ) : (
                                    <p className="bg-muted text-muted-foreground rounded-md px-3 py-2 text-center text-xs">
                                        {t('checkout.payments_unavailable')}
                                    </p>
                                ))}

                            <p className="text-muted-foreground flex items-center justify-center gap-1.5 text-center text-[11px]">
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

/**
 * The card step. Lives inside <Elements> so it can use the Stripe hooks.
 * Confirms the PaymentIntent and lets Stripe redirect to the order
 * confirmation page; the shipped webhook then marks the order paid and
 * fulfills it.
 */
function PaymentStep({ confirmationUrl, amountLabel }: { confirmationUrl: string; amountLabel: string }) {
    const { t } = useTranslate();
    const stripe = useStripe();
    const elements = useElements();
    const [paying, setPaying] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const pay = async () => {
        if (!stripe || !elements || paying) return;
        setPaying(true);
        setError(null);

        const { error: stripeError } = await stripe.confirmPayment({
            elements,
            confirmParams: { return_url: confirmationUrl },
        });

        // We only reach here if confirmation failed *before* redirect
        // (validation / card errors). On success Stripe redirects away.
        setError(stripeError?.message ?? t('checkout.payment_error'));
        setPaying(false);
    };

    return (
        <div className="space-y-4">
            <PaymentElement />
            {error && <p className="text-destructive text-xs">{error}</p>}
            <Button type="button" size="lg" className="w-full" disabled={!stripe || paying} onClick={pay}>
                {paying ? t('checkout.processing') : t('checkout.pay_now', { amount: amountLabel })}
            </Button>
        </div>
    );
}
