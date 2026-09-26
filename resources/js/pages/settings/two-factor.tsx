import { useConfirmDialog } from '@/components/confirm-dialog';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Loader2, ShieldCheck, ShieldOff, ShieldQuestion, Sparkles } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface TwoFactorPageProps {
    enabled: boolean;
    pending: boolean;
    qr_svg: string | null;
    secret: string | null;
    recovery_codes: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Two-factor authentication', href: '/settings/two-factor' },
];

export default function TwoFactor({ enabled, pending, qr_svg, secret, recovery_codes }: TwoFactorPageProps) {
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const [showCodes, setShowCodes] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Two-factor · Settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Two-factor authentication"
                        description="Add a second sign-in step using a time-based one-time code from your authenticator app."
                    />

                    {flash?.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                            {flash.success}
                        </div>
                    )}

                    {/* Status pill */}
                    <div className="flex items-center gap-3 rounded-lg border bg-card p-4 shadow-sm">
                        <span
                            className={
                                'flex size-10 shrink-0 items-center justify-center rounded-full border ' +
                                (enabled
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : pending
                                      ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300'
                                      : 'border-border bg-muted/40 text-muted-foreground')
                            }
                        >
                            {enabled ? (
                                <ShieldCheck className="size-5" />
                            ) : pending ? (
                                <ShieldQuestion className="size-5" />
                            ) : (
                                <ShieldOff className="size-5" />
                            )}
                        </span>
                        <div className="flex-1">
                            <div className="text-sm font-medium">
                                {enabled ? '2FA is active' : pending ? '2FA setup pending — confirm a code' : '2FA is off'}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {enabled
                                    ? 'You will need a 6-digit code on every sign-in.'
                                    : pending
                                      ? 'Scan the QR with your authenticator app, then enter a code below.'
                                      : 'Sign-ins use just your password right now.'}
                            </div>
                        </div>
                    </div>

                    {!enabled && !pending && <EnableSection />}
                    {pending && <PendingSection qrSvg={qr_svg} secret={secret} recoveryCodes={recovery_codes} />}
                    {enabled && (
                        <>
                            <RecoveryCodesSection codes={recovery_codes} show={showCodes} onToggle={() => setShowCodes((s) => !s)} />
                            <DisableSection />
                        </>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function EnableSection() {
    const { post, processing } = useForm({});
    return (
        <div className="rounded-lg border bg-card p-5 shadow-sm">
            <p className="text-sm">
                Click below to generate a new secret. You'll then scan a QR with an authenticator app (Authy, 1Password,
                Google Authenticator) and confirm a code to finish setup.
            </p>
            <Button
                className="mt-4"
                disabled={processing}
                onClick={() => post(route('two-factor.enable'), { preserveScroll: true })}
            >
                {processing ? <Loader2 className="animate-spin" /> : <Sparkles />}
                Set up two-factor
            </Button>
        </div>
    );
}

function PendingSection({
    qrSvg,
    secret,
    recoveryCodes,
}: {
    qrSvg: string | null;
    secret: string | null;
    recoveryCodes: string[];
}) {
    const { data, setData, post, processing, errors } = useForm({ code: '' });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.confirm'), { preserveScroll: true });
    };

    return (
        <div className="space-y-4 rounded-lg border bg-card p-5 shadow-sm">
            <div className="grid gap-6 sm:grid-cols-[200px_1fr]">
                {qrSvg && (
                    <div className="space-y-2">
                        <div className="rounded-lg border bg-white p-2" dangerouslySetInnerHTML={{ __html: qrSvg }} />
                        <p className="text-center font-mono text-[10px] text-muted-foreground">Scan with authenticator</p>
                    </div>
                )}
                <div className="space-y-3">
                    <div>
                        <Label className="text-xs">Manual entry key</Label>
                        <div className="mt-1 rounded-md border bg-muted/40 px-3 py-2 font-mono text-xs break-all">{secret}</div>
                    </div>
                    {recoveryCodes.length > 0 && (
                        <div>
                            <Label className="text-xs">Recovery codes (save these!)</Label>
                            <ul className="mt-1 grid grid-cols-2 gap-1.5 rounded-md border bg-muted/40 p-3 font-mono text-[11px]">
                                {recoveryCodes.map((c) => (
                                    <li key={c}>{c}</li>
                                ))}
                            </ul>
                            <p className="mt-1 text-[11px] text-muted-foreground">
                                These won't be shown in full again. Store them in a password manager.
                            </p>
                        </div>
                    )}
                </div>
            </div>

            <form onSubmit={submit} className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-end">
                <div className="flex-1 space-y-1.5">
                    <Label htmlFor="code" className="text-xs">Confirmation code</Label>
                    <Input
                        id="code"
                        inputMode="numeric"
                        pattern="\d{6}"
                        maxLength={6}
                        autoComplete="one-time-code"
                        placeholder="123456"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.replace(/\D/g, ''))}
                    />
                    <InputError message={errors.code} />
                </div>
                <Button type="submit" disabled={processing || data.code.length !== 6}>
                    {processing ? <Loader2 className="animate-spin" /> : null}
                    Confirm & activate
                </Button>
            </form>
        </div>
    );
}

function RecoveryCodesSection({
    codes,
    show,
    onToggle,
}: {
    codes: string[];
    show: boolean;
    onToggle: () => void;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const regenerate: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.recovery-codes'), {
            preserveScroll: true,
            onSuccess: () => reset('password'),
        });
    };

    return (
        <div className="space-y-4 rounded-lg border bg-card p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-sm font-semibold">Recovery codes</h3>
                    <p className="text-xs text-muted-foreground">
                        Single-use backups in case you lose access to your authenticator.
                    </p>
                </div>
                <Button size="sm" variant="ghost" onClick={onToggle}>
                    {show ? 'Hide' : 'Show'} codes
                </Button>
            </div>

            {show && (
                <ul className="grid grid-cols-2 gap-1.5 rounded-md border bg-muted/40 p-3 font-mono text-[11px]">
                    {codes.length === 0 ? (
                        <li className="col-span-2 text-muted-foreground">All codes used. Regenerate below.</li>
                    ) : (
                        codes.map((c) => <li key={c}>{c}</li>)
                    )}
                </ul>
            )}

            <form onSubmit={regenerate} className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-end">
                <div className="flex-1 space-y-1.5">
                    <Label htmlFor="password" className="text-xs">Confirm with password</Label>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>
                <Button type="submit" variant="outline" disabled={processing}>
                    {processing ? <Loader2 className="animate-spin" /> : null}
                    Regenerate codes
                </Button>
            </form>
        </div>
    );
}

function DisableSection() {
    const { data, setData, delete: destroy, processing, errors, reset } = useForm({ password: '' });
    const { ask, confirmDialog } = useConfirmDialog();

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        ask({
            title: 'Disable two-factor authentication?',
            description: 'Signing in will only need your password again, so your account will be less secure.',
            confirmLabel: 'Disable 2FA',
            destructive: true,
            action: (finish) =>
                destroy(route('two-factor.disable'), {
                    preserveScroll: true,
                    onSuccess: () => reset('password'),
                    onFinish: finish,
                }),
        });
    };

    return (
        <div className="space-y-4 rounded-lg border border-destructive/30 bg-destructive/[0.04] p-5 shadow-sm">
            <div>
                <h3 className="text-sm font-semibold text-destructive">Disable two-factor</h3>
                <p className="text-xs text-muted-foreground">
                    You'll go back to password-only sign-in. We recommend leaving 2FA on.
                </p>
            </div>
            <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div className="flex-1 space-y-1.5">
                    <Label htmlFor="password-disable" className="text-xs">Current password</Label>
                    <Input
                        id="password-disable"
                        type="password"
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>
                <Button type="submit" variant="destructive" disabled={processing}>
                    {processing ? <Loader2 className="animate-spin" /> : null}
                    Disable 2FA
                </Button>
            </form>
            {confirmDialog}
        </div>
    );
}
