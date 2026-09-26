import { useConfirmDialog } from '@/components/confirm-dialog';
import { type StripeWebhookInfo } from '@/components/stripe-webhook-steps';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, CreditCard, ExternalLink, Sparkles } from 'lucide-react';
import PaymentGatewayCard, { type WorkspacePaymentGateway } from './payment-gateway-card';

interface Subscription {
    status: 'trialing' | 'active' | 'past_due' | 'cancelled' | 'expired';
    billing_cycle: 'monthly' | 'annual';
    current_period_end: string | null;
    trial_ends_at: string | null;
    cancel_at: string | null;
    plan: {
        slug: string | null;
        name: string | null;
    };
}

interface Plan {
    slug: string;
    name: string;
    description: string | null;
    monthly_cents: number;
    annual_cents: number;
    limits: Record<string, number | null>;
    features: string[];
}

interface BillingProps {
    subscription: Subscription | null;
    plans: Plan[];
    usage: Record<string, { used: number; limit: number | null; remaining: number | null }>;
    is_stripe_configured: boolean;
    can_manage_payments: boolean;
    payment_gateway: WorkspacePaymentGateway | null;
    stripe_webhook: StripeWebhookInfo | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/workspace/projects' },
    { title: 'Billing', href: '/workspace/billing' },
];

function money(cents: number): string {
    return cents === 0
        ? 'Free'
        : new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(cents / 100);
}

const STATUS_VARIANT: Record<Subscription['status'], 'default' | 'secondary' | 'outline' | 'destructive'> = {
    trialing: 'secondary',
    active: 'default',
    past_due: 'destructive',
    cancelled: 'outline',
    expired: 'outline',
};

export default function WorkspaceBillingIndex({
    subscription,
    plans,
    usage,
    is_stripe_configured,
    can_manage_payments,
    payment_gateway,
    stripe_webhook,
}: BillingProps) {
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;

    const { ask, confirmDialog } = useConfirmDialog();

    const switchTo = (plan: Plan, cycle: 'monthly' | 'annual') =>
        ask({
            title: `Switch to ${plan.name}?`,
            description: `Your workspace moves to the ${plan.name} plan, billed ${cycle} at ${money(cycle === 'annual' ? plan.annual_cents : plan.monthly_cents)}${cycle === 'annual' ? '/yr' : '/mo'}.`,
            confirmLabel: `Switch to ${plan.name}`,
            action: (finish) => router.post(route('workspace.billing.change'), { plan: plan.slug, cycle }, { onFinish: finish }),
        });

    const portal = () => router.post(route('workspace.billing.portal'));
    const cancel = () => {
        ask({
            title: 'Cancel your subscription?',
            description: 'It stays active until the end of the current billing period, then the workspace returns to the free plan.',
            confirmLabel: 'Cancel subscription',
            destructive: true,
            action: (finish) => router.post(route('workspace.billing.cancel'), {}, { onFinish: finish }),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspace · Billing" />

            <div className="mx-auto w-full max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-4 py-2 text-sm">{flash.error}</div>
                )}

                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">Billing</h1>
                    <p className="text-muted-foreground mt-1 text-sm">Manage your plan, view usage, and access invoices.</p>
                </div>

                {/* PAYMENT GATEWAY — the Stripe keys this workspace's checkout charges with */}
                {can_manage_payments && stripe_webhook && <PaymentGatewayCard gateway={payment_gateway} webhook={stripe_webhook} />}

                {!is_stripe_configured && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200">
                        <p className="mb-1 inline-flex items-center gap-1.5 font-medium">
                            <AlertTriangle className="size-4" /> Plan upgrades aren’t available yet
                        </p>
                        <p className="text-xs">
                            Changing your workspace plan is billed by the platform, whose billing isn’t connected on this server yet. This doesn’t
                            affect your store’s own payment gateway above.
                        </p>
                    </div>
                )}

                {/* CURRENT SUBSCRIPTION */}
                {subscription && (
                    <section className="bg-card rounded-xl border p-5 shadow-sm sm:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="font-display inline-flex items-center gap-2 text-base font-semibold tracking-tight">
                                    <CreditCard className="size-4" /> Current plan
                                </h2>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <span className="font-display text-2xl font-semibold">{subscription.plan.name ?? '—'}</span>
                                    <Badge variant={STATUS_VARIANT[subscription.status]} className="capitalize">
                                        {subscription.status.replace('_', ' ')}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Billed {subscription.billing_cycle}.
                                    {subscription.current_period_end && (
                                        <> Renews {new Date(subscription.current_period_end).toLocaleDateString()}.</>
                                    )}
                                    {subscription.cancel_at && <> Cancels on {new Date(subscription.cancel_at).toLocaleDateString()}.</>}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {is_stripe_configured && (
                                    <Button variant="outline" size="sm" onClick={portal}>
                                        <ExternalLink className="size-3.5" /> Manage in Stripe
                                    </Button>
                                )}
                                {is_stripe_configured && subscription.status === 'active' && !subscription.cancel_at && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={cancel}
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    >
                                        Cancel subscription
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* USAGE */}
                        <div className="mt-5 grid gap-3 border-t pt-4 sm:grid-cols-2 lg:grid-cols-4">
                            {Object.entries(usage).map(([resource, snap]) => (
                                <div key={resource} className="space-y-1">
                                    <div className="text-muted-foreground flex items-center justify-between text-xs">
                                        <span className="font-mono capitalize">{resource.replace('_', ' ')}</span>
                                        <span className="tabular-nums">
                                            {snap.used}
                                            {snap.limit !== null && <span className="text-muted-foreground/70"> / {snap.limit}</span>}
                                        </span>
                                    </div>
                                    {snap.limit !== null && (
                                        <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                                            <div
                                                className={cn(
                                                    'h-full rounded-full transition-all',
                                                    snap.used >= snap.limit ? 'bg-destructive' : 'bg-primary',
                                                )}
                                                style={{ width: `${Math.min(100, (snap.used / Math.max(1, snap.limit)) * 100)}%` }}
                                            />
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* PLAN PICKER */}
                <section>
                    <h2 className="font-display mb-3 text-base font-semibold tracking-tight">Plans</h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {plans.map((plan) => {
                            const isCurrent = subscription?.plan.slug === plan.slug;
                            const canSwitch = is_stripe_configured && !isCurrent && (plan.slug === 'pro' || plan.slug === 'business');
                            return (
                                <article
                                    key={plan.slug}
                                    className={cn(
                                        'bg-card flex flex-col rounded-xl border p-5',
                                        isCurrent ? 'border-primary/50 ring-primary/30 ring-1' : 'border-border',
                                    )}
                                >
                                    <header className="mb-3">
                                        <h3 className="font-display text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                                            {plan.name}
                                        </h3>
                                        <div className="mt-2 flex items-baseline gap-1">
                                            <span className="font-display text-2xl font-semibold">{money(plan.monthly_cents)}</span>
                                            {plan.monthly_cents > 0 && <span className="text-muted-foreground text-xs">/mo</span>}
                                        </div>
                                        {plan.description && <p className="text-muted-foreground mt-2 text-xs">{plan.description}</p>}
                                    </header>
                                    <ul className="mb-4 flex-1 space-y-1.5 text-xs">
                                        {plan.features.slice(0, 6).map((feat) => (
                                            <li key={feat} className="flex items-start gap-1.5">
                                                <CheckCircle2 className="text-primary mt-0.5 size-3 shrink-0" />
                                                <span className="capitalize">{feat.replace(/_/g, ' ')}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    {isCurrent ? (
                                        <Button size="sm" disabled variant="secondary">
                                            Current plan
                                        </Button>
                                    ) : canSwitch ? (
                                        <Button size="sm" onClick={() => switchTo(plan, 'monthly')}>
                                            <Sparkles /> Upgrade
                                        </Button>
                                    ) : (
                                        <Button size="sm" variant="outline" disabled>
                                            {plan.slug === 'enterprise' ? 'Contact sales' : 'Unavailable'}
                                        </Button>
                                    )}
                                </article>
                            );
                        })}
                    </div>
                </section>
            </div>

            {confirmDialog}
        </AppLayout>
    );
}
