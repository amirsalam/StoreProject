import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, TrendingDown, TrendingUp } from 'lucide-react';
import * as React from 'react';

/**
 * Widget primitives shared by every role's dashboard.
 *
 * The data shape is the WidgetPayload protocol from
 * docs/dashboard-architecture.md §3. Pages receive a `widgets` array
 * via Inertia props and map each entry to <Widget />.
 */
export interface CtaMeta {
    href: string;
    label: string;
}

export interface WidgetMeta {
    sparkline?: number[];
    delta?: { value: number; period: string };
    cta?: CtaMeta;
}

export interface StatWidget {
    type: 'stat';
    key: string;
    title: string;
    data: { value: number; format: 'money' | 'integer' | 'percent'; currency?: string; display?: string };
    meta?: WidgetMeta;
}

export interface ChartWidget {
    type: 'chart';
    key: string;
    title: string;
    data: { kind: 'line' | 'bar' | 'donut' | 'area'; series: { name: string; points: [string, number][] }[] };
    meta?: WidgetMeta;
}

export interface TableWidget {
    type: 'table';
    key: string;
    title: string;
    data: {
        columns: { key: string; label: string; align?: 'left' | 'right'; format?: string }[];
        rows: Record<string, unknown>[];
    };
    meta?: WidgetMeta;
}

export interface QuickActionsWidget {
    type: 'quick-actions';
    key: string;
    title: string;
    data: { actions: { label: string; href: string; icon: string; primary?: boolean }[] };
    meta?: WidgetMeta;
}

export type WidgetPayload = StatWidget | ChartWidget | TableWidget | QuickActionsWidget;

/* ────────────────────────────────────────────────────────────────────── */

export function Widget({ widget }: { widget: WidgetPayload }) {
    switch (widget.type) {
        case 'stat':
            return <StatCard widget={widget} />;
        case 'chart':
            return <ChartCard widget={widget} />;
        case 'table':
            return <TableCard widget={widget} />;
        case 'quick-actions':
            return <QuickActionsCard widget={widget} />;
    }
}

function formatValue(data: StatWidget['data']): string {
    if (data.display) return data.display;
    switch (data.format) {
        case 'money':
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: data.currency ?? 'USD',
            }).format(data.value / 100);
        case 'percent':
            return `${data.value.toFixed(1)}%`;
        default:
            return new Intl.NumberFormat('en-US').format(data.value);
    }
}

function StatCard({ widget }: { widget: StatWidget }) {
    const delta = widget.meta?.delta;
    return (
        <Card>
            <div className="flex items-start justify-between gap-2">
                <p className="font-mono text-[11px] uppercase tracking-wider text-muted-foreground">
                    {widget.title}
                </p>
                {widget.meta?.cta && (
                    <Link
                        href={widget.meta.cta.href}
                        className="text-muted-foreground hover:text-foreground"
                        aria-label={widget.meta.cta.label}
                    >
                        <ArrowUpRight className="size-4" />
                    </Link>
                )}
            </div>
            <p className="mt-2 font-display text-3xl font-semibold tabular-nums tracking-tight sm:text-4xl">
                {formatValue(widget.data)}
            </p>
            {delta && (
                <div
                    className={cn(
                        'mt-1 inline-flex items-center gap-1 text-xs font-medium',
                        delta.value >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400',
                    )}
                >
                    {delta.value >= 0 ? <TrendingUp className="size-3" /> : <TrendingDown className="size-3" />}
                    {Math.abs(delta.value).toFixed(1)}% vs {delta.period}
                </div>
            )}
        </Card>
    );
}

function ChartCard({ widget }: { widget: ChartWidget }) {
    const series = widget.data.series[0]?.points ?? [];
    const max = Math.max(1, ...series.map(([, v]) => v));
    return (
        <Card className="col-span-full lg:col-span-2">
            <div className="mb-4 flex items-baseline justify-between gap-3">
                <h3 className="font-display text-sm font-semibold tracking-tight">{widget.title}</h3>
                {widget.meta?.cta && <CardCta cta={widget.meta.cta} />}
            </div>
            {series.length === 0 ? (
                <EmptyHint>No data yet — run `php artisan metrics:rollup` to populate.</EmptyHint>
            ) : (
                <div className="flex h-32 items-end gap-1 sm:h-40">
                    {series.map(([date, value], i) => (
                        <div key={i} className="flex flex-1 flex-col items-center gap-1">
                            <div
                                className="w-full rounded-sm bg-gradient-to-t from-primary/60 to-primary transition-all hover:from-primary hover:to-fuchsia-500"
                                style={{ height: `${Math.max(2, (value / max) * 100)}%` }}
                                title={`${date}: ${value}`}
                            />
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

function TableCard({ widget }: { widget: TableWidget }) {
    const { columns, rows } = widget.data;
    return (
        <Card className="col-span-full">
            <div className="mb-4 flex items-baseline justify-between gap-3">
                <h3 className="font-display text-sm font-semibold tracking-tight">{widget.title}</h3>
                {widget.meta?.cta && <CardCta cta={widget.meta.cta} />}
            </div>
            {rows.length === 0 ? (
                <EmptyHint>No rows yet.</EmptyHint>
            ) : (
                <div className="-mx-2 overflow-x-auto sm:mx-0">
                    <table className="w-full text-sm">
                        <thead className="text-left text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                            <tr>
                                {columns.map((c) => (
                                    <th
                                        key={c.key}
                                        className={cn(
                                            'px-2 py-2 sm:px-3',
                                            c.align === 'right' ? 'text-right' : 'text-left',
                                        )}
                                    >
                                        {c.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border/60">
                            {rows.map((row, i) => (
                                <tr key={i} className="hover:bg-muted/30">
                                    {columns.map((c) => {
                                        const v = (row as Record<string, unknown>)[c.key];
                                        return (
                                            <td
                                                key={c.key}
                                                className={cn(
                                                    'px-2 py-2.5 sm:px-3',
                                                    c.align === 'right' ? 'text-right tabular-nums' : '',
                                                    c.format === 'money' ? 'font-medium tabular-nums' : '',
                                                )}
                                            >
                                                {v == null ? '—' : String(v)}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
}

function QuickActionsCard({ widget }: { widget: QuickActionsWidget }) {
    return (
        <Card>
            <h3 className="mb-3 font-display text-sm font-semibold tracking-tight">{widget.title}</h3>
            <div className="flex flex-col gap-2">
                {widget.data.actions.map((a) => (
                    <Button
                        key={a.label}
                        asChild
                        size="sm"
                        variant={a.primary ? 'default' : 'outline'}
                        className="justify-start"
                    >
                        <Link href={a.href}>
                            <ArrowRight />
                            {a.label}
                        </Link>
                    </Button>
                ))}
            </div>
        </Card>
    );
}

/* ────────────────────────────────────────────────────────────────────── */
/*  Building blocks                                                       */
/* ────────────────────────────────────────────────────────────────────── */

function Card({ className, children }: { className?: string; children: React.ReactNode }) {
    return (
        <div className={cn('rounded-xl border bg-card p-4 shadow-sm sm:p-5', className)}>
            {children}
        </div>
    );
}

function CardCta({ cta }: { cta: CtaMeta }) {
    return (
        <Link href={cta.href} className="text-xs font-medium text-primary hover:underline">
            {cta.label} →
        </Link>
    );
}

function EmptyHint({ children }: { children: React.ReactNode }) {
    return (
        <p className="rounded-md border border-dashed bg-muted/30 px-3 py-6 text-center text-xs text-muted-foreground">
            {children}
        </p>
    );
}

/**
 * Generic dashboard scaffold: greeting + responsive grid that lays
 * widgets out in 1/2/3 columns at sm/md/lg.
 */
export function DashboardGrid({
    user,
    widgets,
    intro,
}: {
    user: { name: string; role: string };
    widgets: WidgetPayload[];
    intro?: React.ReactNode;
}) {
    return (
        <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 2xl:max-w-[1400px]">
            <div className="space-y-1">
                <h1 className="font-display text-2xl font-semibold tracking-tight sm:text-3xl">
                    Welcome back, {user.name.split(' ')[0]}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {intro ?? `Your ${user.role.replace('-', ' ')} overview.`}
                </p>
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                {widgets.map((w) => (
                    <Widget key={w.key} widget={w} />
                ))}
            </div>
        </div>
    );
}
