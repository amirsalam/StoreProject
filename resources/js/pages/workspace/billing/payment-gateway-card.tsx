import { StripeWebhookSteps, type StripeWebhookInfo } from '@/components/stripe-webhook-steps';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';
import { FormEvent } from 'react';

export interface WorkspacePaymentGateway {
    environment: 'sandbox' | 'production';
    is_active: boolean;
    publishable_key: string | null;
    has_secret_key: boolean;
    has_webhook_secret: boolean;
    last_connection_at: string | null;
}

/**
 * The workspace's Stripe keys — what its store checkout charges with.
 * Saved encrypted server-side; secret values are never sent back, so a
 * blank secret field on save keeps the stored one.
 */
export default function PaymentGatewayCard({ gateway, webhook }: { gateway: WorkspacePaymentGateway | null; webhook: StripeWebhookInfo }) {
    const form = useForm({
        environment: gateway?.environment ?? 'sandbox',
        publishable_key: gateway?.publishable_key ?? '',
        secret_key: '',
        webhook_secret: '',
        is_active: gateway?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('workspace.billing.gateway.update'), {
            preserveScroll: true,
            onSuccess: () => form.reset('secret_key', 'webhook_secret'),
        });
    };

    const test = () => router.post(route('workspace.billing.gateway.test'), {}, { preserveScroll: true });

    const status = !gateway
        ? { label: 'Not configured', variant: 'outline' as const }
        : !gateway.is_active
          ? { label: 'Inactive', variant: 'outline' as const }
          : gateway.last_connection_at
            ? { label: 'Connected', variant: 'default' as const }
            : { label: 'Active · not tested', variant: 'secondary' as const };

    return (
        <section className="bg-card rounded-xl border p-5 shadow-sm sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-display inline-flex items-center gap-2 text-base font-semibold tracking-tight">
                        <CreditCard className="size-4" /> Payment gateway
                    </h2>
                    <p className="text-muted-foreground mt-1 text-xs">
                        The Stripe account your store checkout charges. Find the keys in the Stripe Dashboard → Developers → API keys.
                    </p>
                </div>
                <Badge variant={status.variant}>{status.label}</Badge>
            </div>

            <form onSubmit={submit} className="mt-5 space-y-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Mode" error={form.errors.environment}>
                        <select
                            value={form.data.environment}
                            onChange={(e) => form.setData('environment', e.target.value as 'sandbox' | 'production')}
                            className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                        >
                            <option value="sandbox">Test (pk_test_ / sk_test_ keys)</option>
                            <option value="production">Live (pk_live_ / sk_live_ keys)</option>
                        </select>
                    </Field>
                    <Field label="Publishable key" error={form.errors.publishable_key}>
                        <Input
                            dir="ltr"
                            autoComplete="off"
                            value={form.data.publishable_key}
                            onChange={(e) => form.setData('publishable_key', e.target.value)}
                            placeholder="pk_test_…"
                            className="font-mono text-xs"
                        />
                    </Field>
                    <Field label="Secret key" error={form.errors.secret_key}>
                        <Input
                            dir="ltr"
                            type="password"
                            autoComplete="off"
                            value={form.data.secret_key}
                            onChange={(e) => form.setData('secret_key', e.target.value)}
                            placeholder={gateway?.has_secret_key ? '•••••••• saved — leave blank to keep' : 'sk_test_…'}
                            className="font-mono text-xs"
                        />
                    </Field>
                    <Field label="Webhook signing secret" error={form.errors.webhook_secret}>
                        <Input
                            dir="ltr"
                            type="password"
                            autoComplete="off"
                            value={form.data.webhook_secret}
                            onChange={(e) => form.setData('webhook_secret', e.target.value)}
                            placeholder={gateway?.has_webhook_secret ? '•••••••• saved — leave blank to keep' : 'whsec_…'}
                            className="font-mono text-xs"
                        />
                    </Field>
                </div>

                <StripeWebhookSteps info={webhook} />

                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(e) => form.setData('is_active', e.target.checked)}
                        className="border-input size-4 rounded"
                    />
                    Accept card payments at checkout
                </label>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                    <p className="text-muted-foreground text-xs">
                        {gateway?.last_connection_at
                            ? `Last verified with Stripe on ${new Date(gateway.last_connection_at).toLocaleString()}.`
                            : 'Save, then use “Test connection” to check the keys with Stripe.'}
                    </p>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={test} disabled={!gateway || form.isDirty}>
                            Test connection
                        </Button>
                        <Button type="submit" size="sm" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save'}
                        </Button>
                    </div>
                </div>
            </form>
        </section>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <Label className="mb-1 block text-xs">{label}</Label>
            {children}
            {error && <p className="text-destructive mt-1 text-xs">{error}</p>}
        </div>
    );
}
