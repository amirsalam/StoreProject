import HeadingSmall from '@/components/heading-small';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Activity, CreditCard, Globe, KeyRound, LogIn, LogOut, ShieldAlert, ShieldCheck, Store, UserPlus } from 'lucide-react';
import { useTranslate } from '@/hooks/use-translate';

interface ActivityEntry {
    id: number;
    event: string;
    description: string | null;
    properties: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
}

interface ActivityIndexProps {
    entries: ActivityEntry[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Activity', href: '/settings/activity' },
];

// Map event keys to friendly labels + icon + tone.
const EVENT_META: Record<string, { label: string; icon: typeof Activity; tone: 'ok' | 'warn' | 'info' }> = {
    'auth.login': { label: 'Signed in', icon: LogIn, tone: 'ok' },
    'auth.logout': { label: 'Signed out', icon: LogOut, tone: 'info' },
    'auth.login.failed': { label: 'Failed sign-in attempt', icon: ShieldAlert, tone: 'warn' },
    'auth.registered': { label: 'Account created', icon: UserPlus, tone: 'info' },
    'password.changed': { label: 'Password changed', icon: ShieldCheck, tone: 'ok' },
    '2fa.enabled': { label: 'Two-factor enabled', icon: ShieldCheck, tone: 'ok' },
    '2fa.disabled': { label: 'Two-factor disabled', icon: ShieldAlert, tone: 'warn' },
    '2fa.challenge.passed': { label: 'Two-factor challenge passed', icon: ShieldCheck, tone: 'ok' },
    '2fa.challenge.failed': { label: 'Two-factor challenge failed', icon: ShieldAlert, tone: 'warn' },
    '2fa.recovery_codes_regenerated': { label: 'Recovery codes regenerated', icon: ShieldCheck, tone: 'info' },
    'session.revoked': { label: 'Session revoked', icon: Globe, tone: 'info' },
    'session.revoked_others': { label: 'Signed out of other devices', icon: Globe, tone: 'info' },
    'api_token.created': { label: 'API token created', icon: KeyRound, tone: 'info' },
    'api_token.revoked': { label: 'API token revoked', icon: KeyRound, tone: 'info' },
    'license.activated': { label: 'License activated', icon: ShieldCheck, tone: 'ok' },
    'license.deactivated': { label: 'License deactivated', icon: ShieldAlert, tone: 'info' },
    'mail_settings.updated': { label: 'Email settings updated', icon: Activity, tone: 'info' },
    'payment_gateway.created': { label: 'Payment gateway created', icon: CreditCard, tone: 'info' },
    'payment_gateway.updated': { label: 'Payment gateway updated', icon: CreditCard, tone: 'info' },
    'payment_gateway.enabled': { label: 'Payment gateway enabled', icon: CreditCard, tone: 'ok' },
    'payment_gateway.disabled': { label: 'Payment gateway disabled', icon: CreditCard, tone: 'warn' },
    'payment_gateway.deleted': { label: 'Payment gateway deleted', icon: CreditCard, tone: 'warn' },
    'payment_gateway.tested': { label: 'Payment gateway tested', icon: CreditCard, tone: 'ok' },
    'payment_gateway.default_set': { label: 'Default payment gateway changed', icon: CreditCard, tone: 'info' },
    'payment_gateway.reordered': { label: 'Payment gateways reordered', icon: CreditCard, tone: 'info' },
    'vendor.registered': { label: 'Store opened', icon: Store, tone: 'ok' },
    'vendor.profile_updated': { label: 'Store profile updated', icon: Store, tone: 'info' },
    'vendor.verified': { label: 'Store verified', icon: Store, tone: 'ok' },
    'vendor.suspended': { label: 'Store suspended', icon: Store, tone: 'warn' },
};

function meta(event: string) {
    return EVENT_META[event] ?? { label: event, icon: Activity, tone: 'info' as const };
}

function relative(iso: string): string {
    const date = new Date(iso);
    // Locale-aware ("5 minutes ago" / "منذ 5 دقائق") via the <html lang> set by useTranslate.
    const rtf = new Intl.RelativeTimeFormat(document.documentElement.lang || undefined, { numeric: 'auto' });
    const minutes = Math.round((date.getTime() - Date.now()) / 60_000);
    if (Math.abs(minutes) < 60) return rtf.format(minutes, 'minute');
    const hours = Math.round(minutes / 60);
    if (Math.abs(hours) < 24) return rtf.format(hours, 'hour');
    const days = Math.round(hours / 24);
    if (Math.abs(days) < 30) return rtf.format(days, 'day');
    return date.toLocaleDateString();
}

export default function ActivityIndex({ entries }: ActivityIndexProps) {
    const { __ } = useTranslate();
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Activity · Settings')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={__('Activity log')}
                        description={__('Recent security events on your account. We keep the last 50 events.')}
                    />

                    {entries.length === 0 ? (
                        <div className="rounded-lg border border-dashed bg-muted/20 p-8 text-center text-sm text-muted-foreground">
                            {__('No recent activity yet.')}
                        </div>
                    ) : (
                        <ul className="space-y-2">
                            {entries.map((entry) => {
                                const m = meta(entry.event);
                                const Icon = m.icon;
                                return (
                                    <li
                                        key={entry.id}
                                        className="flex items-start gap-3 rounded-lg border bg-card p-3 shadow-sm"
                                    >
                                        <span
                                            className={
                                                'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full border ' +
                                                (m.tone === 'ok'
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                    : m.tone === 'warn'
                                                      ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300'
                                                      : 'border-border bg-muted/40 text-muted-foreground')
                                            }
                                        >
                                            <Icon className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                                <span className="text-sm font-medium">{__(m.label)}</span>
                                                <time className="font-mono text-[11px] text-muted-foreground">
                                                    {relative(entry.created_at)}
                                                </time>
                                            </div>
                                            {entry.description && entry.description !== m.label && (
                                                <p className="text-xs text-muted-foreground">{entry.description}</p>
                                            )}
                                            <p className="mt-1 truncate font-mono text-[11px] text-muted-foreground/80">
                                                {entry.ip_address ?? '—'}
                                                {entry.user_agent && <span className="ms-2">{entry.user_agent.slice(0, 80)}</span>}
                                            </p>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
