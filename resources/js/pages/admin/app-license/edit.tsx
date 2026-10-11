import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, ShieldCheck, ShieldOff } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

interface LicenseState {
    activated: boolean;
    activation_id: string | null;
    bound_domain: string | null;
    current_domain: string | null;
    domain_changed: boolean;
    environment: string | null;
    license_status: string | null;
    token_expires_at: string | null;
    last_success_at: string | null;
    last_checked_at: string | null;
    last_error_code: string | null;
    server_configured: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Installation license', href: '/admin/app-license' },
];

function when(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-wrap justify-between gap-2 border-b py-2.5 text-sm last:border-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium break-all">{children}</dd>
        </div>
    );
}

function Notice({ tone, children }: { tone: 'success' | 'error' | 'warning'; children: ReactNode }) {
    const styles = {
        success: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300',
        error: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300',
        warning: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300',
    };

    return <div className={`rounded-md border px-4 py-2 text-sm ${styles[tone]}`}>{children}</div>;
}

export default function AppLicense({ license }: { license: LicenseState }) {
    const { __ } = useTranslate();
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const [confirming, setConfirming] = useState(false);
    const [deactivating, setDeactivating] = useState(false);

    const form = useForm({ license_key: '' });

    const activate = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('admin.app-license.activate'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const deactivate = () => {
        router.post(
            route('admin.app-license.deactivate'),
            {},
            {
                preserveScroll: true,
                onStart: () => setDeactivating(true),
                onFinish: () => {
                    setDeactivating(false);
                    setConfirming(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Installation license')} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && <Notice tone="success">{flash.success}</Notice>}
                {flash?.error && <Notice tone="error">{flash.error}</Notice>}

                <div>
                    <h1 className="inline-flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        {license.activated ? <ShieldCheck className="size-5" /> : <ShieldOff className="size-5" />}
                        {__('Installation license')}
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {__('Activate this StoreProject installation with the license key you received when you bought it.')}
                    </p>
                </div>

                {!license.server_configured && (
                    <Notice tone="warning">{__('The license server address is not configured (APP_LICENSE_SERVER_URL).')}</Notice>
                )}

                {license.domain_changed && (
                    <Notice tone="warning">
                        <span className="inline-flex items-start gap-2">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            {__('This installation was activated for :bound, but APP_URL is now :current. Activate it again for the new address.', {
                                bound: license.bound_domain ?? '',
                                current: license.current_domain ?? '',
                            })}
                        </span>
                    </Notice>
                )}

                <section className="rounded-xl border p-5">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <h2 className="font-semibold">{__('Status')}</h2>
                        {license.activated ? (
                            <Badge className="border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                {__('Activated')}
                            </Badge>
                        ) : (
                            <Badge variant="secondary">{__('Not activated')}</Badge>
                        )}
                    </div>

                    <dl>
                        <Row label={__('This site (APP_URL)')}>{license.current_domain ?? '—'}</Row>
                        {license.activated && (
                            <>
                                <Row label={__('Activated for')}>{license.bound_domain}</Row>
                                <Row label={__('Environment')}>
                                    {license.environment ? __(license.environment === 'production' ? 'Production' : 'Development') : '—'}
                                </Row>
                                <Row label={__('License status')}>{license.license_status ?? '—'}</Row>
                                <Row label={__('Last successful check')}>{when(license.last_success_at)}</Row>
                                <Row label={__('Activation ID')}>
                                    <span className="font-mono text-xs">{license.activation_id}</span>
                                </Row>
                            </>
                        )}
                    </dl>
                </section>

                {!license.activated || license.domain_changed ? (
                    <section className="rounded-xl border p-5">
                        <h2 className="mb-4 font-semibold">{__('Activate')}</h2>
                        <form onSubmit={activate} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="license_key">{__('License key')}</Label>
                                <Input
                                    id="license_key"
                                    value={form.data.license_key}
                                    onChange={(e) => form.setData('license_key', e.target.value)}
                                    placeholder="SP-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX"
                                    className="font-mono"
                                    autoComplete="off"
                                    spellCheck={false}
                                    required
                                />
                                <InputError message={form.errors.license_key} />
                            </div>
                            <Button type="submit" disabled={form.processing || !license.server_configured}>
                                {__('Activate this installation')}
                            </Button>
                        </form>
                    </section>
                ) : (
                    <section className="rounded-xl border p-5">
                        <h2 className="font-semibold">{__('Move to another domain')}</h2>
                        <p className="text-muted-foreground mt-1 mb-4 text-sm">
                            {__('Deactivate here first, then activate the same key on the new installation.')}
                        </p>
                        <Button variant="outline" onClick={() => setConfirming(true)}>
                            {__('Deactivate this installation')}
                        </Button>
                    </section>
                )}
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={__('Deactivate this installation?')}
                description={__('The license is released from :domain so it can be activated elsewhere.', { domain: license.bound_domain ?? '' })}
                confirmLabel={__('Deactivate')}
                destructive
                processing={deactivating}
                onConfirm={deactivate}
            />
        </AppLayout>
    );
}
