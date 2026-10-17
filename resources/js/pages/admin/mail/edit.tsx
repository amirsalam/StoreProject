import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Mail, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { useTranslate } from '@/hooks/use-translate';

type Encryption = 'tls' | 'ssl' | 'none';

interface MailSettingsForm {
    enabled: boolean;
    host: string;
    port: number;
    encryption: Encryption;
    username: string;
    has_password: boolean;
    from_address: string;
    from_name: string;
}

interface Props {
    settings: MailSettingsForm;
    encryptions: Encryption[];
    testRecipient: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Email', href: '/admin/mail' },
];

/** Common providers — fill host/port/encryption; credentials stay with the admin. */
const PRESETS: { label: string; host: string; port: number; encryption: Encryption; hint: string }[] = [
    { label: 'Gmail', host: 'smtp.gmail.com', port: 587, encryption: 'tls', hint: 'Username = your Gmail address; password = a Google “App password” (needs 2-step verification).' },
    { label: 'Outlook / Microsoft 365', host: 'smtp.office365.com', port: 587, encryption: 'tls', hint: 'Username = your full email address.' },
    { label: 'Mailtrap (testing)', host: 'sandbox.smtp.mailtrap.io', port: 2525, encryption: 'tls', hint: 'Emails land in your Mailtrap inbox, not the real recipient — safe for testing.' },
    { label: 'Mailpit (local)', host: '127.0.0.1', port: 1025, encryption: 'none', hint: 'Local test inbox at http://localhost:8025 — no username or password.' },
];

const ENCRYPTION_LABELS: Record<Encryption, string> = {
    tls: 'TLS / STARTTLS (port 587)',
    ssl: 'SSL (port 465)',
    none: 'None',
};

export default function AdminMailSettings({ settings, encryptions, testRecipient }: Props) {
    const { __ } = useTranslate();
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;

    const form = useForm({
        enabled: settings.enabled,
        host: settings.host,
        port: settings.port,
        encryption: settings.encryption,
        username: settings.username,
        password: '',
        from_address: settings.from_address,
        from_name: settings.from_name,
    });

    const test = useForm({ to: testRecipient });

    const save = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('admin.mail.update'), { preserveScroll: true, onSuccess: () => form.reset('password') });
    };

    const sendTest = (e: FormEvent) => {
        e.preventDefault();
        test.post(route('admin.mail.test'), { preserveScroll: true });
    };

    const applyPreset = (preset: (typeof PRESETS)[number]) => {
        form.setData({ ...form.data, host: preset.host, port: preset.port, encryption: preset.encryption });
    };
    const activePreset = PRESETS.find((p) => p.host === form.data.host);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Admin · Email')} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-2 text-sm break-words text-destructive">{flash.error}</div>
                )}

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="inline-flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            <Mail className="size-5" /> {__('Email')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {__('The server that sends order confirmations, invitations and other emails.')}
                        </p>
                    </div>
                    <Badge variant={settings.enabled ? 'default' : 'outline'}>{settings.enabled ? __('Sending via SMTP') : __('Not sending')}</Badge>
                </div>

                <form onSubmit={save} className="space-y-6 rounded-lg border bg-card p-6">
                    <div>
                        <p className="mb-2 text-xs font-medium text-muted-foreground">{__('Quick setup')}</p>
                        <div className="flex flex-wrap gap-2">
                            {PRESETS.map((preset) => (
                                <Button
                                    key={__(preset.label)}
                                    type="button"
                                    size="sm"
                                    variant={activePreset?.label === preset.label ? 'default' : 'outline'}
                                    onClick={() => applyPreset(preset)}
                                >
                                    {__(preset.label)}
                                </Button>
                            ))}
                        </div>
                        {activePreset && <p className="mt-2 text-xs text-muted-foreground">{__(activePreset.hint)}</p>}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={__('SMTP server')} error={form.errors.host}>
                            <Input dir="ltr" value={form.data.host} onChange={(e) => form.setData('host', e.target.value)} placeholder="smtp.gmail.com" />
                        </Field>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={__('Port')} error={form.errors.port}>
                                <Input
                                    dir="ltr"
                                    type="number"
                                    min={1}
                                    max={65535}
                                    value={form.data.port}
                                    onChange={(e) => form.setData('port', Number(e.target.value))}
                                />
                            </Field>
                            <Field label={__('Encryption')} error={form.errors.encryption}>
                                <select
                                    value={form.data.encryption}
                                    onChange={(e) => form.setData('encryption', e.target.value as Encryption)}
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    {encryptions.map((enc) => (
                                        <option key={enc} value={enc}>
                                            {__(ENCRYPTION_LABELS[enc])}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </div>
                        <Field label={__('Username')} error={form.errors.username}>
                            <Input
                                dir="ltr"
                                autoComplete="off"
                                value={form.data.username}
                                onChange={(e) => form.setData('username', e.target.value)}
                                placeholder="you@gmail.com"
                            />
                        </Field>
                        <Field label={__('Password')} error={form.errors.password}>
                            <Input
                                dir="ltr"
                                type="password"
                                autoComplete="new-password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                placeholder={settings.has_password ? __('•••••••• saved — leave blank to keep') : ''}
                            />
                        </Field>
                        <Field label={__('Send from (email address)')} error={form.errors.from_address}>
                            <Input
                                dir="ltr"
                                type="email"
                                value={form.data.from_address}
                                onChange={(e) => form.setData('from_address', e.target.value)}
                                placeholder="orders@yourstore.com"
                            />
                        </Field>
                        <Field label={__('Send from (name)')} error={form.errors.from_name}>
                            <Input value={form.data.from_name} onChange={(e) => form.setData('from_name', e.target.value)} placeholder="StoreProject" />
                        </Field>
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.enabled}
                            onChange={(e) => form.setData('enabled', e.target.checked)}
                            className="size-4 rounded border-input"
                        />
                        {__('Send emails through this server')}
                    </label>

                    <div className="flex justify-end border-t pt-4">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? __('Saving…') : __('Save settings')}
                        </Button>
                    </div>
                </form>

                <form onSubmit={sendTest} className="space-y-3 rounded-lg border bg-card p-6">
                    <div>
                        <h2 className="text-base font-semibold">{__('Send a test email')}</h2>
                        <p className="text-xs text-muted-foreground">{__('Uses the saved settings above — save first if you changed anything.')}</p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input dir="ltr" type="email" value={test.data.to} onChange={(e) => test.setData('to', e.target.value)} className="sm:flex-1" />
                        <Button type="submit" variant="outline" disabled={test.processing || form.isDirty}>
                            <Send className="size-4" /> {test.processing ? __('Sending…') : __('Send test')}
                        </Button>
                    </div>
                    <InputError message={test.errors.to} />
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <Label className="mb-1 block text-xs">{label}</Label>
            {children}
            <InputError message={error} className="mt-1" />
        </div>
    );
}
