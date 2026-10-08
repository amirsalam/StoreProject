import InputError from '@/components/input-error';
import { GitHubIcon, GoogleIcon } from '@/components/social-auth-buttons';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, ExternalLink, Loader2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type ProviderId = 'google' | 'github';

interface ProviderSettings {
    enabled: boolean;
    client_id: string;
    has_secret: boolean;
    from_env: boolean;
    callback_url: string;
}

type FormShape = Record<ProviderId, { enabled: boolean; client_id: string; client_secret: string }>;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Social login', href: '/admin/social-login' },
];

const PROVIDERS: {
    id: ProviderId;
    name: string;
    Icon: typeof GoogleIcon;
    consoleUrl: string;
    consoleLabel: string;
    callbackLabel: string;
}[] = [
    {
        id: 'google',
        name: 'Google',
        Icon: GoogleIcon,
        consoleUrl: 'https://console.cloud.google.com/apis/credentials',
        consoleLabel: 'Google Cloud Console → APIs & Services → Credentials',
        callbackLabel: 'Authorized redirect URI',
    },
    {
        id: 'github',
        name: 'GitHub',
        Icon: GitHubIcon,
        consoleUrl: 'https://github.com/settings/developers',
        consoleLabel: 'GitHub → Settings → Developer settings → OAuth Apps',
        callbackLabel: 'Authorization callback URL',
    },
];

export default function AdminSocialLogin({ settings }: { settings: Record<ProviderId, ProviderSettings> }) {
    const { __, __el } = useTranslate();
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;

    const { data, setData, put, processing, errors } = useForm<FormShape>({
        google: { enabled: settings.google.enabled, client_id: settings.google.client_id, client_secret: '' },
        github: { enabled: settings.github.enabled, client_id: settings.github.client_id, client_secret: '' },
    });
    const errorFor = (id: ProviderId, field: string) => (errors as Record<string, string | undefined>)[`${id}.${field}`];

    const update = (id: ProviderId, patch: Partial<FormShape[ProviderId]>) => setData(id, { ...data[id], ...patch });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.social-login.update'), {
            preserveScroll: true,
            // Keep the switches and IDs; only clear the typed secrets.
            onSuccess: () =>
                setData((d) => ({
                    google: { ...d.google, client_secret: '' },
                    github: { ...d.github, client_secret: '' },
                })),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Social login · Admin')} />

            <div className="mx-auto w-full max-w-4xl space-y-6 px-4 py-6 sm:py-8">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">{__('Social login')}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {__(
                            'The “Continue with Google / GitHub” buttons on the login and register pages. A button is shown only when it is switched on and has a client ID and secret.',
                        )}
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-6">
                    {PROVIDERS.map(({ id, name, Icon, consoleUrl, consoleLabel, callbackLabel }) => {
                        const saved = settings[id];
                        const values = data[id];
                        const hasId = values.client_id.trim() !== '' || saved.from_env;
                        const hasSecret = values.client_secret.trim() !== '' || saved.has_secret || saved.from_env;
                        const status = !values.enabled ? 'off' : hasId && hasSecret ? 'on' : 'incomplete';

                        return (
                            <section key={id} className="border-border bg-card space-y-5 rounded-xl border p-6 shadow-sm sm:p-8">
                                <header className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex items-center gap-3">
                                        <span className="bg-muted/50 flex size-10 items-center justify-center rounded-lg border">
                                            <Icon className="size-5" />
                                        </span>
                                        <h2 className="font-display text-base font-semibold tracking-tight">{name}</h2>
                                    </div>
                                    <OnOffSwitch
                                        checked={values.enabled}
                                        onChange={(enabled) => update(id, { enabled })}
                                        label={__('Show the “Continue with :provider” button', { provider: name })}
                                    />
                                </header>

                                <StatusLine
                                    tone={status === 'on' ? 'ok' : status === 'off' && !(hasId && hasSecret) ? 'muted' : 'warn'}
                                    text={
                                        status === 'on'
                                            ? __('The “Continue with :provider” button is shown on the login and register pages.', { provider: name })
                                            : status === 'incomplete'
                                              ? __('Needs a client ID and secret')
                                              : hasId && hasSecret
                                                ? __('Keys are saved, but the button is switched off. Switch it on and save to show it.')
                                                : __('Off — the button is hidden on the login and register pages.')
                                    }
                                    unsaved={values.enabled !== saved.enabled}
                                />

                                <ol className="text-muted-foreground list-decimal space-y-1 ps-5 text-sm">
                                    <li>
                                        {__el('Create an OAuth app in :console.', {
                                            console: (
                                                <a
                                                    href={consoleUrl}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-primary inline-flex items-center gap-1 underline-offset-4 hover:underline"
                                                >
                                                    {consoleLabel}
                                                    <ExternalLink className="size-3" />
                                                </a>
                                            ),
                                        })}
                                    </li>
                                    <li>{__('Paste the address below as its “:label”.', { label: callbackLabel })}</li>
                                    <li>{__('Copy the client ID and client secret it gives you into the fields below, then save.')}</li>
                                </ol>

                                <div className="space-y-1.5">
                                    <Label>{callbackLabel}</Label>
                                    <CopyField value={saved.callback_url} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor={`${id}-client-id`}>{__('Client ID')}</Label>
                                        <Input
                                            id={`${id}-client-id`}
                                            value={values.client_id}
                                            onChange={(e) => update(id, { client_id: e.target.value })}
                                            autoComplete="off"
                                            spellCheck={false}
                                            placeholder={saved.from_env ? __('Using the value from .env') : ''}
                                        />
                                        <InputError message={errorFor(id, 'client_id')} />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor={`${id}-client-secret`}>{__('Client secret')}</Label>
                                        <Input
                                            id={`${id}-client-secret`}
                                            type="password"
                                            value={values.client_secret}
                                            onChange={(e) => update(id, { client_secret: e.target.value })}
                                            autoComplete="new-password"
                                            spellCheck={false}
                                            placeholder={
                                                saved.has_secret
                                                    ? __('Saved — leave empty to keep it')
                                                    : saved.from_env
                                                      ? __('Using the value from .env')
                                                      : ''
                                            }
                                        />
                                        <InputError message={errorFor(id, 'client_secret')} />
                                    </div>
                                </div>
                            </section>
                        );
                    })}

                    <div className="flex justify-end">
                        <Button type="submit" size="lg" disabled={processing}>
                            {processing && <Loader2 className="animate-spin" />}
                            {__('Save changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

/** A real on/off switch — the state reads "On" / "Off" next to it. */
function OnOffSwitch({ checked, onChange, label }: { checked: boolean; onChange: (value: boolean) => void; label: string }) {
    const { __ } = useTranslate();
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            title={label}
            onClick={() => onChange(!checked)}
            className={cn(
                'focus-visible:ring-ring inline-flex items-center gap-2 rounded-full border px-1.5 py-1 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:outline-hidden',
                checked
                    ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                    : 'border-border bg-muted/40 text-muted-foreground hover:text-foreground',
            )}
        >
            <span className={cn('relative h-5 w-9 rounded-full transition-colors', checked ? 'bg-emerald-500' : 'bg-muted-foreground/30')}>
                <span
                    className={cn('absolute top-0.5 size-4 rounded-full bg-white shadow transition-all', checked ? 'start-[1.125rem]' : 'start-0.5')}
                />
            </span>
            <span className="pe-1.5">{checked ? __('On') : __('Off')}</span>
        </button>
    );
}

function StatusLine({ tone, text, unsaved }: { tone: 'ok' | 'warn' | 'muted'; text: string; unsaved: boolean }) {
    const { __ } = useTranslate();
    return (
        <div
            className={cn(
                'rounded-md border px-3 py-2 text-sm',
                tone === 'ok' &&
                    'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300',
                tone === 'warn' && 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300',
                tone === 'muted' && 'border-border bg-muted/30 text-muted-foreground',
            )}
        >
            {text}
            {unsaved && <span className="ms-1 font-medium">{__('(not saved yet — click “Save changes”)')}</span>}
        </div>
    );
}

function CopyField({ value }: { value: string }) {
    const { __ } = useTranslate();
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // Clipboard blocked — the field is selectable as a fallback.
        }
    };

    return (
        <div className="flex gap-2">
            <Input value={value} readOnly dir="ltr" className="font-mono text-xs" onFocus={(e) => e.currentTarget.select()} />
            <Button type="button" variant="outline" onClick={copy} className="shrink-0">
                {copied ? <Check /> : <Copy />}
                {copied ? __('Copied') : __('Copy')}
            </Button>
        </div>
    );
}
