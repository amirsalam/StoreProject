import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated, type PaginatedLink } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { useTranslate } from '@/hooks/use-translate';

interface Option {
    value: string;
    label: string;
}

interface OrderRow {
    id: number;
    order_number: string;
    customer_name: string;
    customer_email: string;
    items: { title: string; quantity: number }[];
    total: string;
    currency: string;
    status: string;
    payment: { status: string; gateway: string; reference: string | null } | null;
    created_at: string;
    paid_at: string | null;
}

interface AdminOrdersIndexProps {
    orders: Paginated<OrderRow>;
    filters: { search: string; status: string };
    statuses: Option[];
    summary: { paid_revenue: string; paid: number; pending: number; refunded: number };
    mailConfigured: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Orders', href: '/admin/orders' },
];

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    paid: 'default',
    succeeded: 'default',
    pending: 'secondary',
    failed: 'destructive',
    refunded: 'outline',
    cancelled: 'outline',
};

function money(amount: string, currency = 'USD'): string {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(Number(amount));
}

function itemsLabel(items: OrderRow['items']): string {
    if (items.length === 0) return '—';
    const first = `${items[0].title}${items[0].quantity > 1 ? ` ×${items[0].quantity}` : ''}`;
    return items.length > 1 ? `${first} +${items.length - 1}` : first;
}

export default function AdminOrdersIndex({ orders, filters, statuses, summary, mailConfigured }: AdminOrdersIndexProps) {
    const { __ } = useTranslate();
    const [search, setSearch] = useState(filters.search);

    const applyFilter = (next: Partial<AdminOrdersIndexProps['filters']>) => {
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries({ ...filters, ...next })) {
            if (v) params[k] = String(v);
        }
        router.get(route('admin.orders.index'), params, { preserveScroll: true, preserveState: true, replace: true });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Admin · Orders')} />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{__('Orders')}</h1>
                    <p className="text-sm text-muted-foreground">{__('Every order and its payment, live from the database.')}</p>
                </div>

                {!mailConfigured && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                        <p>
                            <strong>{__('Customers aren’t receiving order emails.')}</strong> {__('Email sending isn’t set up, so confirmations are only written to the server log.')}
                        </p>
                        <Button asChild size="sm" variant="outline">
                            <Link href={route('admin.mail.edit')}>{__('Set up email')}</Link>
                        </Button>
                    </div>
                )}

                {/* Summary */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <SummaryCard label={__('Paid revenue')} value={money(summary.paid_revenue)} />
                    <SummaryCard label={__('Paid orders')} value={String(summary.paid)} />
                    <SummaryCard label={__('Awaiting payment')} value={String(summary.pending)} />
                    <SummaryCard label={__('Refunded')} value={String(summary.refunded)} />
                </div>

                <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <Input
                        type="search"
                        placeholder={__('Order number, name or email…')}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full sm:w-72"
                    />
                    <select
                        value={filters.status}
                        onChange={(e) => applyFilter({ status: e.target.value })}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">{__('All statuses')}</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {__(s.label)}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="secondary">
                        {__('Filter')}
                    </Button>
                </form>

                {/* Desktop / tablet: data table */}
                <div className="hidden overflow-hidden rounded-lg border bg-card md:block">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-start text-xs uppercase tracking-wider text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-start font-medium">{__('Order')}</th>
                                <th className="px-4 py-3 text-start font-medium">{__('Customer')}</th>
                                <th className="px-4 py-3 text-start font-medium">{__('Items')}</th>
                                <th className="px-4 py-3 text-start font-medium">{__('Total')}</th>
                                <th className="px-4 py-3 text-start font-medium">{__('Status')}</th>
                                <th className="px-4 py-3 text-start font-medium">{__('Payment')}</th>
                                <th className="px-4 py-3 text-start font-medium">{__('Date')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {orders.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-12 text-center text-muted-foreground">
                                        {__('No orders match these filters.')}
                                    </td>
                                </tr>
                            ) : (
                                orders.data.map((order) => (
                                    <tr key={order.id} className="align-top hover:bg-muted/30">
                                        <td className="px-4 py-3 font-mono text-xs" dir="ltr">
                                            {order.order_number}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">{order.customer_name}</div>
                                            <div className="text-xs text-muted-foreground">{order.customer_email}</div>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">{itemsLabel(order.items)}</td>
                                        <td className="px-4 py-3 font-medium tabular-nums">{money(order.total, order.currency)}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant={STATUS_VARIANT[order.status] ?? 'outline'} className="capitalize">
                                                {__(order.status)}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <PaymentCell payment={order.payment} />
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {new Date(order.created_at).toLocaleString()}
                                            {order.paid_at && <div>{__('Paid :date', { date: new Date(order.paid_at).toLocaleString() })}</div>}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Mobile: stacked cards */}
                <div className="space-y-3 md:hidden">
                    {orders.data.length === 0 ? (
                        <div className="rounded-lg border border-dashed bg-card p-8 text-center text-sm text-muted-foreground">
                            {__('No orders match these filters.')}
                        </div>
                    ) : (
                        orders.data.map((order) => (
                            <div key={order.id} className="space-y-2 rounded-lg border bg-card p-4 shadow-sm">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="truncate font-mono text-xs" dir="ltr">
                                            {order.order_number}
                                        </div>
                                        <div className="truncate font-medium">{order.customer_name}</div>
                                        <div className="truncate text-xs text-muted-foreground">{order.customer_email}</div>
                                    </div>
                                    <div className="shrink-0 text-end">
                                        <div className="font-semibold tabular-nums">{money(order.total, order.currency)}</div>
                                        <Badge variant={STATUS_VARIANT[order.status] ?? 'outline'} className="mt-1 capitalize">
                                            {__(order.status)}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="text-sm text-muted-foreground">{itemsLabel(order.items)}</div>
                                <div className="flex items-center justify-between gap-3 border-t pt-2 text-xs text-muted-foreground">
                                    <PaymentCell payment={order.payment} />
                                    <span>{new Date(order.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {orders.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-center gap-1">
                        {orders.links.map((link, idx) => (
                            <PaginationLink key={idx} link={link} />
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}

function SummaryCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <p className="font-mono text-[11px] uppercase tracking-wider text-muted-foreground">{label}</p>
            <p className="mt-1 text-xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}

function PaymentCell({ payment }: { payment: OrderRow['payment'] }) {
    const { __ } = useTranslate();
    if (!payment) {
        return <span className="text-xs text-muted-foreground">—</span>;
    }

    return (
        <div className="space-y-1">
            <Badge variant={STATUS_VARIANT[payment.status] ?? 'outline'} className="capitalize">
                {__(payment.status)}
            </Badge>
            {payment.reference && (
                <div className="font-mono text-[11px] text-muted-foreground" dir="ltr" title={__('Stripe PaymentIntent — search it in the Stripe Dashboard')}>
                    {payment.reference}
                </div>
            )}
        </div>
    );
}

function PaginationLink({ link }: { link: PaginatedLink }) {
    const className = `min-w-9 rounded-md border px-3 py-1.5 text-sm transition ${
        link.active ? 'border-primary bg-primary text-primary-foreground' : link.url ? 'border-input hover:bg-accent' : 'border-transparent text-muted-foreground'
    }`;

    if (!link.url) {
        return <span className={className} dangerouslySetInnerHTML={{ __html: link.label }} />;
    }

    return <Link href={link.url} preserveScroll preserveState className={className} dangerouslySetInnerHTML={{ __html: link.label }} />;
}
