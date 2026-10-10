import { Container, Section } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { cn } from '@/lib/utils';
import { type SharedData, type SystemStatusState } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';

interface StatusComponent {
    key: string;
    status: SystemStatusState;
    latency_ms: number;
}

interface PageProps {
    status: {
        overall: SystemStatusState;
        checked_at: string;
        components: StatusComponent[];
    };
    refreshSeconds: number;
}

export default function Status({ status, refreshSeconds }: PageProps) {
    const { branding, locale } = usePage<SharedData>().props;
    const { t } = useTranslate();
    const brandTitle = branding?.title ?? 'StoreProject';
    const healthy = status.overall === 'operational';
    const checkedAt = new Date(status.checked_at).toLocaleString(locale);

    return (
        <StorefrontLayout>
            <Head title={`${t('status_page.meta_title')} — ${brandTitle}`} />

            <Section className="pt-16 pb-24 sm:pt-20 sm:pb-32">
                <Container>
                    <div className="mx-auto max-w-3xl">
                        <header className="flex flex-col gap-3">
                            <h1 className="font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{t('status_page.title')}</h1>
                            <p className="text-muted-foreground text-pretty sm:text-lg">{t('status_page.lead')}</p>
                        </header>

                        <div
                            role="status"
                            className={cn(
                                'mt-8 flex items-center gap-3 rounded-xl border p-5',
                                healthy
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200'
                                    : 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200',
                            )}
                        >
                            {healthy ? <CheckCircle2 className="size-6 shrink-0" /> : <AlertTriangle className="size-6 shrink-0" />}
                            <p className="text-lg font-semibold">{t(`status_page.overall.${status.overall}`)}</p>
                        </div>

                        <ul className="border-border/60 divide-border/60 mt-8 divide-y rounded-xl border">
                            {status.components.map((component) => {
                                const ok = component.status === 'operational';
                                return (
                                    <li key={component.key} className="flex items-start justify-between gap-4 p-5">
                                        <div>
                                            <p className="font-semibold">{t(`status_page.components.${component.key}.name`)}</p>
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                {t(`status_page.components.${component.key}.description`)}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1">
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                    ok
                                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                        : 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
                                                )}
                                            >
                                                <span className={cn('size-1.5 rounded-full', ok ? 'bg-emerald-500' : 'bg-amber-500')} />
                                                {t(`status_page.state.${component.status}`)}
                                            </span>
                                            <span className="text-muted-foreground font-mono text-[11px] tabular-nums">
                                                {t('status_page.latency', { ms: component.latency_ms })}
                                            </span>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>

                        <p className="text-muted-foreground mt-6 text-sm">
                            {t('status_page.checked_at', { time: checkedAt, seconds: refreshSeconds })}
                        </p>
                        <p className="text-muted-foreground mt-2 text-sm">
                            {t('status_page.health_endpoint')}{' '}
                            <code dir="ltr" className="font-mono">
                                /up
                            </code>
                        </p>
                    </div>
                </Container>
            </Section>
        </StorefrontLayout>
    );
}
