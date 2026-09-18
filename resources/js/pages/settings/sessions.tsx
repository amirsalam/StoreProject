import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Globe, Loader2, MonitorSmartphone, ShieldAlert, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface SessionRow {
    id: string;
    ip_address: string | null;
    device: string;
    is_current: boolean;
    last_active_at: number;
}

interface SessionsIndexProps {
    sessions: SessionRow[];
    driver: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sessions', href: '/settings/sessions' },
];

function relative(unixTs: number): string {
    const diff = Date.now() / 1000 - unixTs;
    if (diff < 60) return 'active now';
    if (diff < 3600) return `${Math.round(diff / 60)}m ago`;
    if (diff < 86_400) return `${Math.round(diff / 3600)}h ago`;
    return `${Math.round(diff / 86_400)}d ago`;
}

export default function SessionsIndex({ sessions, driver }: SessionsIndexProps) {
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const onSignOutOthers: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('sessions.destroy-other'), {
            preserveScroll: true,
            onSuccess: () => reset('password'),
        });
    };

    const revoke = (id: string) => {
        if (!confirm('Revoke this session? The signed-in device will be logged out immediately.')) return;
        router.delete(route('sessions.destroy', id), { preserveScroll: true });
    };

    const dbDriver = driver === 'database';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sessions · Settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Active sessions"
                        description="Devices that are currently signed in to your account. Revoke any you don't recognize."
                    />

                    {!dbDriver && (
                        <div className="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                            <ShieldAlert className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                Session driver is <code className="font-mono">{driver}</code>. List/revoke per device requires the
                                <code className="mx-1 font-mono">database</code>session driver. Sign out of other devices still works.
                            </span>
                        </div>
                    )}

                    {flash?.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                            {flash.error}
                        </div>
                    )}

                    {sessions.length === 0 ? (
                        <div className="rounded-lg border border-dashed bg-muted/20 p-8 text-center text-sm text-muted-foreground">
                            No active sessions found.
                        </div>
                    ) : (
                        <ul className="space-y-2">
                            {sessions.map((s) => (
                                <li
                                    key={s.id}
                                    className="flex flex-wrap items-center gap-3 rounded-lg border bg-card p-3 shadow-sm sm:flex-nowrap"
                                >
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted/50 text-muted-foreground">
                                        <MonitorSmartphone className="size-4" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-baseline gap-2">
                                            <span className="text-sm font-medium">{s.device}</span>
                                            {s.is_current && (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wider text-primary">
                                                    This device
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap gap-3 font-mono text-[11px] text-muted-foreground">
                                            <span className="inline-flex items-center gap-1">
                                                <Globe className="size-3" />
                                                {s.ip_address ?? '—'}
                                            </span>
                                            <span>{relative(s.last_active_at)}</span>
                                        </div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        disabled={s.is_current}
                                        onClick={() => revoke(s.id)}
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive disabled:text-muted-foreground"
                                    >
                                        <Trash2 />
                                        <span className="ms-1">Revoke</span>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="space-y-4 rounded-lg border bg-card p-5">
                        <div>
                            <h3 className="text-sm font-semibold">Sign out of all other devices</h3>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Enter your password to confirm. This won't sign you out on this device.
                            </p>
                        </div>
                        <form onSubmit={onSignOutOthers} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="flex-1 space-y-1.5">
                                <Label htmlFor="password" className="text-xs">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                                <InputError message={errors.password} />
                            </div>
                            <Button type="submit" variant="outline" disabled={processing} className="sm:shrink-0">
                                {processing ? <Loader2 className="animate-spin" /> : null}
                                Sign out others
                            </Button>
                        </form>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
