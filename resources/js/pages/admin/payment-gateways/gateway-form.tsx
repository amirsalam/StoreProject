import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type PaymentProvider } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';

export interface GatewayFormValues {
    id?: number;
    provider: string;
    name: string;
    display_name: string;
    description: string | null;
    logo: string | null;
    environment: 'sandbox' | 'production';
    is_active: boolean;
    is_default: boolean;
    credential_status?: Record<string, boolean>;
    has_webhook_secret?: boolean;
    supported_currencies: string[];
    supported_countries: string[];
    fee_fixed: string | number;
    fee_percent: string | number;
    min_amount: string | number | null;
    max_amount: string | number | null;
    sort_order: number;
}

interface Props {
    providers: PaymentProvider[];
    gateway?: GatewayFormValues;
}

export default function GatewayForm({ providers, gateway }: Props) {
    const isEdit = Boolean(gateway?.id);

    const form = useForm({
        provider: gateway?.provider ?? providers[0]?.value ?? 'stripe',
        name: gateway?.name ?? '',
        display_name: gateway?.display_name ?? '',
        description: gateway?.description ?? '',
        logo: gateway?.logo ?? '',
        environment: gateway?.environment ?? 'sandbox',
        is_active: gateway?.is_active ?? false,
        is_default: gateway?.is_default ?? false,
        credentials: {} as Record<string, string>,
        webhook_secret: '',
        supported_currencies: (gateway?.supported_currencies ?? []).join(', '),
        supported_countries: (gateway?.supported_countries ?? []).join(', '),
        fee_fixed: gateway?.fee_fixed ?? '0',
        fee_percent: gateway?.fee_percent ?? '0',
        min_amount: gateway?.min_amount ?? '',
        max_amount: gateway?.max_amount ?? '',
        sort_order: gateway?.sort_order ?? 0,
    });

    const activeProvider = useMemo(
        () => providers.find((p) => p.value === form.data.provider),
        [providers, form.data.provider],
    );

    const submit = (e: FormEvent) => {
        e.preventDefault();

        const toList = (s: string) =>
            s.split(',').map((x) => x.trim()).filter(Boolean);

        form.transform((data) => ({
            ...data,
            supported_currencies: toList(String(data.supported_currencies)).map((c) => c.toUpperCase()),
            supported_countries: toList(String(data.supported_countries)).map((c) => c.toUpperCase()),
            min_amount: data.min_amount === '' ? null : data.min_amount,
            max_amount: data.max_amount === '' ? null : data.max_amount,
        }));

        if (isEdit && gateway?.id) {
            form.put(route('admin.payment-gateways.update', gateway.id));
        } else {
            form.post(route('admin.payment-gateways.store'));
        }
    };

    const setCredential = (key: string, value: string) =>
        form.setData('credentials', { ...form.data.credentials, [key]: value });

    return (
        <form onSubmit={submit} className="space-y-6">
            {/* Provider + identity */}
            <section className="rounded-lg border bg-card p-6">
                <h2 className="mb-4 text-base font-semibold">Provider</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Provider" error={form.errors.provider}>
                        {isEdit ? (
                            <Input value={activeProvider?.label ?? form.data.provider} readOnly className="bg-muted/40" />
                        ) : (
                            <select
                                value={form.data.provider}
                                onChange={(e) => form.setData('provider', e.target.value)}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                {providers.map((p) => (
                                    <option key={p.value} value={p.value}>
                                        {p.logo ? `${p.logo} ` : ''}
                                        {p.label}
                                    </option>
                                ))}
                            </select>
                        )}
                    </Field>
                    <Field label="Environment" error={form.errors.environment}>
                        <select
                            value={form.data.environment}
                            onChange={(e) => form.setData('environment', e.target.value as 'sandbox' | 'production')}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="sandbox">Sandbox (test)</option>
                            <option value="production">Production (live)</option>
                        </select>
                    </Field>
                    <Field label="Gateway name (internal)" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Display name (customer-facing)" error={form.errors.display_name}>
                        <Input
                            value={form.data.display_name}
                            onChange={(e) => form.setData('display_name', e.target.value)}
                            required
                        />
                    </Field>
                    <Field label="Logo / icon (emoji or URL)" error={form.errors.logo} className="sm:col-span-2">
                        <Input value={form.data.logo} onChange={(e) => form.setData('logo', e.target.value)} placeholder="💳" />
                    </Field>
                    <Field label="Description" error={form.errors.description} className="sm:col-span-2">
                        <textarea
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            rows={2}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                    </Field>
                </div>
            </section>

            {/* Credentials (dynamic per provider) */}
            {(activeProvider?.fields.length ?? 0) > 0 && (
                <section className="rounded-lg border bg-card p-6">
                    <h2 className="text-base font-semibold">API credentials</h2>
                    <p className="mb-4 text-xs text-muted-foreground">
                        Stored encrypted. {isEdit && 'Leave a field blank to keep its current value.'}
                    </p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {activeProvider?.fields.map((f) => {
                            const configured = gateway?.credential_status?.[f.key];
                            return (
                                <Field
                                    key={f.key}
                                    label={`${f.label}${f.required && !isEdit ? ' *' : ''}`}
                                    error={form.errors[`credentials.${f.key}` as keyof typeof form.errors] as string}
                                >
                                    <Input
                                        type={f.secret ? 'password' : 'text'}
                                        autoComplete="off"
                                        value={form.data.credentials[f.key] ?? ''}
                                        onChange={(e) => setCredential(f.key, e.target.value)}
                                        placeholder={configured ? '•••••••• configured' : ''}
                                        required={f.required && !isEdit}
                                    />
                                </Field>
                            );
                        })}
                    </div>
                </section>
            )}

            {/* Webhook */}
            {activeProvider?.supports_webhook && (
                <section className="rounded-lg border bg-card p-6">
                    <h2 className="mb-4 text-base font-semibold">Webhook</h2>
                    <Field label="Webhook secret" error={form.errors.webhook_secret}>
                        <Input
                            type="password"
                            autoComplete="off"
                            value={form.data.webhook_secret}
                            onChange={(e) => form.setData('webhook_secret', e.target.value)}
                            placeholder={gateway?.has_webhook_secret ? '•••••••• configured' : ''}
                        />
                    </Field>
                </section>
            )}

            {/* Commercial settings */}
            <section className="rounded-lg border bg-card p-6">
                <h2 className="mb-4 text-base font-semibold">Fees, limits & coverage</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Transaction fee — percentage (%)" error={form.errors.fee_percent}>
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.fee_percent}
                            onChange={(e) => form.setData('fee_percent', e.target.value)}
                        />
                    </Field>
                    <Field label="Transaction fee — fixed" error={form.errors.fee_fixed}>
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.fee_fixed}
                            onChange={(e) => form.setData('fee_fixed', e.target.value)}
                        />
                    </Field>
                    <Field label="Minimum amount" error={form.errors.min_amount}>
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.min_amount ?? ''}
                            onChange={(e) => form.setData('min_amount', e.target.value)}
                        />
                    </Field>
                    <Field label="Maximum amount" error={form.errors.max_amount}>
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.max_amount ?? ''}
                            onChange={(e) => form.setData('max_amount', e.target.value)}
                        />
                    </Field>
                    <Field label="Supported currencies (comma-separated)" error={form.errors.supported_currencies}>
                        <Input
                            value={form.data.supported_currencies}
                            onChange={(e) => form.setData('supported_currencies', e.target.value)}
                            placeholder="USD, EUR, SAR"
                        />
                    </Field>
                    <Field label="Supported countries (comma-separated)" error={form.errors.supported_countries}>
                        <Input
                            value={form.data.supported_countries}
                            onChange={(e) => form.setData('supported_countries', e.target.value)}
                            placeholder="US, GB, SA"
                        />
                    </Field>
                    <Field label="Sort order" error={form.errors.sort_order}>
                        <Input
                            type="number"
                            min="0"
                            value={form.data.sort_order}
                            onChange={(e) => form.setData('sort_order', Number(e.target.value))}
                        />
                    </Field>
                </div>

                <div className="mt-4 flex flex-col gap-2">
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                            className="size-4 rounded border-input"
                        />
                        Active (available at checkout)
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_default}
                            onChange={(e) => form.setData('is_default', e.target.checked)}
                            className="size-4 rounded border-input"
                        />
                        Set as the default gateway
                    </label>
                </div>
            </section>

            <div className="flex items-center justify-end gap-2">
                <Button asChild variant="ghost">
                    <Link href={route('admin.payment-gateways.index')}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : isEdit ? 'Save changes' : 'Create gateway'}
                </Button>
            </div>
        </form>
    );
}

function Field({
    label,
    error,
    children,
    className = '',
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={className}>
            <Label className="mb-1 block text-xs">{label}</Label>
            {children}
            {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
        </div>
    );
}
