import { useConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, FileText, Send, ShoppingBag, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Invoice {
    id: number;
    number: string;
    status: 'draft' | 'sent' | 'paid' | 'overdue' | 'void';
    subtotal_cents: number;
    tax_cents: number;
    total_cents: number;
    currency: string;
    issued_on: string;
    due_on: string;
    sent_at: string | null;
    paid_at: string | null;
    client?: { id: number; name: string; email: string } | null;
    /** Set for invoices issued automatically for a paid store order. */
    order_id: number | null;
    order?: { id: number; order_number: string } | null;
}

interface Option {
    value: string;
    label: string;
}

interface Filters {
    search: string;
    status: string;
}

interface InvoicesIndexProps {
    invoices: Paginated<Invoice>;
    filters: Filters;
    statuses: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/workspace/projects' },
    { title: 'Invoices', href: '/workspace/invoices' },
];

/** Amounts are stored in the currency's minor unit (cents; whole yen for JPY). */
function money(minor: number, currency = 'USD', locale = 'en'): string {
    try {
        const format = new Intl.NumberFormat(locale, { style: 'currency', currency });
        const digits = format.resolvedOptions().maximumFractionDigits ?? 2;
        return format.format(minor / 10 ** digits);
    } catch {
        return `${currency} ${(minor / 100).toFixed(2)}`;
    }
}

export default function WorkspaceInvoicesIndex({ invoices, filters, statuses }: InvoicesIndexProps) {
    const { __, locale } = useTranslate();
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const [search, setSearch] = useState(filters.search);

    const applyFilter = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries(merged)) {
            if (v) params[k] = String(v);
        }
        router.get(route('workspace.invoices.index'), params, { preserveScroll: true, preserveState: true, replace: true });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    const markSent = (invoice: Invoice) => router.post(route('workspace.invoices.send', invoice.id), {}, { preserveScroll: true });
    const markPaid = (invoice: Invoice) => router.post(route('workspace.invoices.paid', invoice.id), {}, { preserveScroll: true });

    const { ask, confirmDialog } = useConfirmDialog();

    const remove = (invoice: Invoice) => {
        ask({
            title: __('Delete this invoice?'),
            description: __('Invoice :number will be permanently deleted. This cannot be undone.', { number: invoice.number }),
            confirmLabel: __('Delete invoice'),
            destructive: true,
            action: (finish) => router.delete(route('workspace.invoices.destroy', invoice.id), { preserveScroll: true, onFinish: finish }),
        });
    };

    const totalOutstanding = invoices.data.filter((i) => i.status === 'sent' || i.status === 'overdue').reduce((sum, i) => sum + i.total_cents, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Workspace · Invoices')} />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="border-destructive/30 bg-destructive/10 text-destructive rounded-md border px-4 py-2 text-sm">{flash.error}</div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">{__('Invoices')}</h1>
                        <p className="text-muted-foreground text-sm">
                            {invoices.total === 1 ? __('1 invoice') : __(':count invoices', { count: invoices.total })}
                            {totalOutstanding > 0 && (
                                <>
                                    {' '}
                                    · <span className="text-foreground font-medium">{money(totalOutstanding, 'USD', locale)}</span>{' '}
                                    {__('outstanding')}
                                </>
                            )}
                        </p>
                    </div>
                </div>

                <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <Input
                        type="search"
                        placeholder={__('Search by number, client or order…')}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full sm:w-72"
                    />
                    <select
                        value={filters.status}
                        onChange={(e) => applyFilter({ status: e.target.value })}
                        className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">{__('All statuses')}</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="secondary">
                        {__('Filter')}
                    </Button>
                </form>

                {invoices.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="bg-card overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left text-xs tracking-wider uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">{__('Number')}</th>
                                    <th className="px-4 py-3 font-medium">{__('Client')}</th>
                                    <th className="px-4 py-3 font-medium">{__('Status')}</th>
                                    <th className="px-4 py-3 font-medium">{__('Issued')}</th>
                                    <th className="px-4 py-3 font-medium">{__('Due')}</th>
                                    <th className="px-4 py-3 text-right font-medium">{__('Total')}</th>
                                    <th className="px-4 py-3 text-right font-medium">{__('Actions')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {invoices.data.map((inv) => (
                                    <tr key={inv.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3 font-mono text-[12px] whitespace-nowrap">{inv.number}</td>
                                        <td className="px-4 py-3">
                                            {inv.client ? (
                                                <div>
                                                    <div className="truncate font-medium">{inv.client.name}</div>
                                                    <div className="text-muted-foreground truncate text-xs">{inv.client.email}</div>
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                            {inv.order && (
                                                <div className="text-muted-foreground mt-0.5 inline-flex items-center gap-1 text-xs">
                                                    <ShoppingBag className="size-3" />
                                                    <span className="font-mono">{inv.order.order_number}</span>
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={inv.status} />
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3 tabular-nums">
                                            {new Date(inv.issued_on).toLocaleDateString()}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3 tabular-nums">{new Date(inv.due_on).toLocaleDateString()}</td>
                                        <td className="font-display px-4 py-3 text-right tabular-nums">
                                            {money(inv.total_cents, inv.currency, locale)}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button asChild size="sm" variant="ghost">
                                                    <a href={route('invoices.show', inv.id)} target="_blank" rel="noopener">
                                                        <ExternalLink className="size-3.5" /> {__('View')}
                                                    </a>
                                                </Button>
                                                {inv.status === 'draft' && (
                                                    <Button size="sm" variant="ghost" onClick={() => markSent(inv)}>
                                                        <Send className="size-3.5" /> {__('Send')}
                                                    </Button>
                                                )}
                                                {(inv.status === 'sent' || inv.status === 'overdue') && (
                                                    <Button size="sm" variant="ghost" onClick={() => markPaid(inv)}>
                                                        <CheckCircle2 className="size-3.5" /> {__('Mark paid')}
                                                    </Button>
                                                )}
                                                {/* Store-order invoices are sales records: a refund voids them. */}
                                                {!inv.order_id && (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => remove(inv)}
                                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {confirmDialog}
        </AppLayout>
    );
}

function StatusBadge({ status }: { status: 'draft' | 'sent' | 'paid' | 'overdue' | 'void' }) {
    const { __ } = useTranslate();
    const variant: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
        draft: 'secondary',
        sent: 'default',
        paid: 'default',
        overdue: 'destructive',
        void: 'outline',
    };
    return (
        <Badge variant={variant[status] ?? 'secondary'} className="capitalize">
            {__(status)}
        </Badge>
    );
}

function EmptyState() {
    const { __ } = useTranslate();
    return (
        <div className="border-border/80 bg-muted/20 rounded-xl border border-dashed px-6 py-20 text-center">
            <div className="border-border/80 bg-background text-muted-foreground mx-auto mb-5 inline-flex size-12 items-center justify-center rounded-full border">
                <FileText className="size-5" />
            </div>
            <h2 className="font-display text-lg font-semibold tracking-tight">{__('No invoices yet')}</h2>
            <p className="text-muted-foreground mx-auto mt-1 max-w-sm text-sm">
                {__(
                    'An invoice is created automatically for every paid store order. Orders still waiting for payment get one as soon as they are paid.',
                )}
            </p>
        </div>
    );
}
