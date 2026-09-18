import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Copy, KeyRound, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface ApiToken {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string;
}

interface ApiTokensPageProps {
    tokens: ApiToken[];
    abilities: Record<string, string>;
    newToken: string | null;
    newTokenName: string | null;
}

interface FormShape {
    name: string;
    abilities: string[];
    [key: string]: string | string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'API tokens', href: '/settings/api-tokens' },
];

export default function ApiTokens() {
    const { tokens, abilities, newToken, newTokenName } = usePage<ApiTokensPageProps>().props;
    const [copied, setCopied] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<FormShape>({
        name: '',
        abilities: ['read'],
    });

    useEffect(() => {
        if (!copied) return;
        const t = setTimeout(() => setCopied(false), 2000);
        return () => clearTimeout(t);
    }, [copied]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('api-tokens.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const toggleAbility = (key: string) => {
        const next = data.abilities.includes(key)
            ? data.abilities.filter((a) => a !== key)
            : [...data.abilities, key];
        setData('abilities', next);
    };

    const revoke = (token: ApiToken) => {
        if (!confirm(`Revoke "${token.name}"? Apps using it will stop working immediately.`)) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route('api-tokens.destroy', token.id);
        const csrf = document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content;
        if (csrf) {
            const tok = document.createElement('input');
            tok.type = 'hidden';
            tok.name = '_token';
            tok.value = csrf;
            form.appendChild(tok);
        }
        const method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'DELETE';
        form.appendChild(method);
        document.body.appendChild(form);
        form.submit();
    };

    const copy = async () => {
        if (!newToken) return;
        await navigator.clipboard.writeText(newToken);
        setCopied(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="API tokens" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="API tokens"
                        description="Personal access tokens authenticate against the /api/v1 endpoints. Send each token as a Bearer header."
                    />

                    {/* One-time freshly issued token banner */}
                    {newToken && (
                        <section className="space-y-3 rounded-xl border border-primary/40 bg-primary/[0.06] p-5">
                            <div className="flex items-center gap-2">
                                <KeyRound className="size-4 text-primary" />
                                <h3 className="font-display text-sm font-semibold tracking-tight">
                                    Token <span className="font-mono">{newTokenName}</span> created
                                </h3>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Copy this token now — it won't be shown again. Treat it like a password.
                            </p>
                            <div className="flex items-stretch gap-2">
                                <code className="flex-1 truncate rounded-md border border-border bg-background px-3 py-2 font-mono text-xs">
                                    {newToken}
                                </code>
                                <Button type="button" onClick={copy} size="sm" variant="outline" className="shrink-0">
                                    {copied ? <CheckCircle2 className="text-emerald-600" /> : <Copy />}
                                    {copied ? 'Copied' : 'Copy'}
                                </Button>
                            </div>
                            <pre className="overflow-x-auto rounded-md border border-border/60 bg-background/60 p-3 text-[11px] text-muted-foreground">
{`curl -H "Authorization: Bearer ${newToken.slice(0, 12)}..." \\
  https://${window.location.host}/api/v1/user`}
                            </pre>
                        </section>
                    )}

                    {/* Create token form */}
                    <form onSubmit={submit} className="space-y-4 rounded-xl border bg-card p-5 sm:p-6">
                        <h3 className="font-display text-sm font-semibold tracking-tight">Create a new token</h3>

                        <div className="space-y-1.5">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                placeholder="e.g. CI deploy, mobile app"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                maxLength={80}
                                required
                                autoComplete="off"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <fieldset className="space-y-3">
                            <legend className="text-sm font-medium">Abilities</legend>
                            <div className="grid gap-2 sm:grid-cols-3">
                                {Object.entries(abilities).map(([key, label]) => {
                                    const checked = data.abilities.includes(key);
                                    return (
                                        <label
                                            key={key}
                                            className={cn(
                                                'flex cursor-pointer items-start gap-3 rounded-md border p-3 transition-colors',
                                                checked
                                                    ? 'border-primary/50 bg-primary/[0.04]'
                                                    : 'border-border hover:bg-muted/50',
                                            )}
                                        >
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={() => toggleAbility(key)}
                                                aria-label={key}
                                            />
                                            <div className="min-w-0">
                                                <div className="font-mono text-xs font-semibold uppercase tracking-wider">
                                                    {key}
                                                </div>
                                                <div className="text-xs text-muted-foreground">{label}</div>
                                            </div>
                                        </label>
                                    );
                                })}
                            </div>
                            <InputError message={errors.abilities} />
                        </fieldset>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing || data.abilities.length === 0}>
                                <KeyRound />
                                Create token
                            </Button>
                        </div>
                    </form>

                    {/* Existing tokens */}
                    <div className="space-y-3">
                        <h3 className="font-display text-sm font-semibold tracking-tight">Active tokens</h3>
                        {tokens.length === 0 ? (
                            <div className="rounded-xl border border-dashed bg-muted/20 p-8 text-center text-sm text-muted-foreground">
                                No tokens yet. Create one above to authenticate against the API.
                            </div>
                        ) : (
                            <ul className="overflow-hidden rounded-xl border bg-card divide-y">
                                {tokens.map((t) => (
                                    <li key={t.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="min-w-0 space-y-1">
                                            <div className="truncate font-medium">{t.name}</div>
                                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                <span className="font-mono">
                                                    {t.last_used_at ? `Last used ${relative(t.last_used_at)}` : 'Never used'}
                                                </span>
                                                <span aria-hidden>·</span>
                                                <span className="font-mono">Created {relative(t.created_at)}</span>
                                            </div>
                                            <div className="flex flex-wrap gap-1.5">
                                                {t.abilities.map((a) => (
                                                    <Badge key={a} variant="secondary" className="font-mono text-[10px] uppercase tracking-wider">
                                                        {a}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => revoke(t)}
                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        >
                                            <Trash2 />
                                            Revoke
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function relative(iso: string): string {
    const date = new Date(iso);
    const diff = Date.now() - date.getTime();
    const minutes = Math.round(diff / 60_000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.round(hours / 24);
    if (days < 30) return `${days}d ago`;
    return date.toLocaleDateString();
}
