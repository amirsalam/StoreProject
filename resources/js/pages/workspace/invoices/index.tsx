import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, FileText, Send, Trash2 } from 'lucide-react';
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

function money(cents: number, currency = 'USD'): string {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
    } catch {
        return `$${(cents / 100).toFixed(2)}`;
    }
}

export default function WorkspaceInvoicesIndex({ invoices, filters, statuses }: InvoicesIndexProps) {
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

    const remove = (invoice: Invoice) => {
        if (!confirm(`Delete invoice ${invoice.number}?`)) return;
        router.delete(route('workspace.invoices.destroy', invoice.id), { preserveScroll: true });
    };

    const totalOutstanding = invoices.data
        .filter((i) => i.status === 'sent' || i.status === 'overdue')
        .reduce((sum, i) => sum + i.total_cents, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspace · Invoices" />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">Invoices</h1>
                        <p className="text-sm text-muted-foreground">
                            {invoices.total} {invoices.total === 1 ? 'invoice' : 'invoices'}
                            {totalOutstanding > 0 && (
                                <> · <span className="font-medium text-foreground">{money(totalOutstanding)}</span> outstanding</>
                            )}
                        </p>
                    </div>
                </div>

                <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <Input
                        type="search"
                        placeholder="Search by number or client…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full sm:w-72"
                    />
                    <select
                        value={filters.status}
                        onChange={(e) => applyFilter({ status: e.target.value })}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">All statuses</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>{s.label}</option>
                        ))}
                    </select>
                    <Button type="submit" variant="secondary">Filter</Button>
                </form>

                {invoices.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="overflow-hidden rounded-lg border bg-card">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Number</th>
                                    <th className="px-4 py-3 font-medium">Client</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 font-medium">Issued</th>
                                    <th className="px-4 py-3 font-medium">Due</th>
                                    <th className="px-4 py-3 font-medium text-right">Total</th>
                                    <th className="px-4 py-3 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {invoices.data.map((inv) => (
                                    <tr key={inv.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3 font-mono text-[12px]">{inv.number}</td>
                                        <td className="px-4 py-3">
                                            {inv.client ? (
                                                <div>
                                                    <div className="truncate font-medium">{inv.client.name}</div>
                                                    <div className="truncate text-xs text-muted-foreground">{inv.client.email}</div>
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={inv.status} />
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-muted-foreground">
                                            {new Date(inv.issued_on).toLocaleDateString()}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-muted-foreground">
                                            {new Date(inv.due_on).toLocaleDateString()}
                                        </td>
                                        <td className="px-4 py-3 text-right font-display tabular-nums">
                                            {money(inv.total_cents, inv.currency)}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                {inv.status === 'draft' && (
                                                    <Button size="sm" variant="ghost" onClick={() => markSent(inv)}>
                                                        <Send className="size-3.5" /> Send
                                                    </Button>
                                                )}
                                                {(inv.status === 'sent' || inv.status === 'overdue') && (
                                                    <Button size="sm" variant="ghost" onClick={() => markPaid(inv)}>
                                                        <CheckCircle2 className="size-3.5" /> Mark paid
                                                    </Button>
                                                )}
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => remove(inv)}
                                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function StatusBadge({ status }: { status: 'draft' | 'sent' | 'paid' | 'overdue' | 'void' }) {
    const variant: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
        draft: 'secondary',
        sent: 'default',
        paid: 'default',
        overdue: 'destructive',
        void: 'outline',
    };
    return (
        <Badge variant={variant[status] ?? 'secondary'} className="capitalize">
            {status}
        </Badge>
    );
}

function EmptyState() {
    return (
        <div className="rounded-xl border border-dashed border-border/80 bg-muted/20 px-6 py-20 text-center">
            <div className="mx-auto mb-5 inline-flex size-12 items-center justify-center rounded-full border border-border/80 bg-background text-muted-foreground">
                <FileText className="size-5" />
            </div>
            <h2 className="font-display text-lg font-semibold tracking-tight">No invoices yet</h2>
            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                Bill your clients for completed work. Invoices live alongside your projects.
            </p>
        </div>
    );
}
