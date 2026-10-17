import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated, type VendorStatus } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { BadgeCheck, ExternalLink } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface VendorRow {
    id: number;
    name: string;
    slug: string;
    status: VendorStatus;
    is_verified: boolean;
    products_count: number;
    created_at: string;
    company_name: string | null;
    owner: { name: string; email: string } | null;
    store_url: string | null;
}

type ApprovalMode = 'manual' | 'auto';

interface AdminVendorsIndexProps {
    vendors: Paginated<VendorRow>;
    filters: { search: string; status: '' | VendorStatus };
    counts: Record<VendorStatus, number>;
    approvalMode: ApprovalMode;
    canConfigureMode: boolean;
}

type ReasonAction = 'reject' | 'suspend';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Vendors', href: '/admin/vendors' },
];

const STATUS_TABS: { value: '' | VendorStatus; label: string }[] = [
    { value: '', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'rejected', label: 'Rejected' },
];

export default function AdminVendorsIndex({ vendors, filters, counts, approvalMode, canConfigureMode }: AdminVendorsIndexProps) {
    const { flash, errors } = usePage<{
        flash: { success: string | null; error: string | null };
        errors: Record<string, string>;
    }>().props;
    const [search, setSearch] = useState(filters.search);
    const [reasonFor, setReasonFor] = useState<{ vendor: VendorRow; action: ReasonAction } | null>(null);

    const total = Object.values(counts).reduce((a, b) => a + b, 0);

    const applyFilter = (next: Partial<AdminVendorsIndexProps['filters']>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries(merged)) if (v) params[k] = String(v);
        router.get(route('admin.vendors.index'), params, { preserveScroll: true, preserveState: true, replace: true });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    const act = (vendor: VendorRow, action: 'approve' | 'reinstate' | 'verify') => {
        router.post(route(`admin.vendors.${action}`, vendor.id), {}, { preserveScroll: true });
    };

    const setMode = (mode: ApprovalMode) => {
        if (mode === approvalMode) return;
        router.put(route('admin.vendors.approval-mode'), { mode }, { preserveScroll: true });
    };

    const error = errors.vendor ?? errors.mode ?? errors.reason;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vendors · Admin" />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}
                {error && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/[0.06] px-4 py-2 text-sm text-destructive">{error}</div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">Vendors</h1>
                        <p className="text-sm text-muted-foreground">
                            {total} {total === 1 ? 'store' : 'stores'} · {counts.pending} awaiting review
                        </p>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <span className="font-mono text-[11px] uppercase tracking-wider text-muted-foreground">New vendors</span>
                        <div className="inline-flex rounded-md border p-0.5" role="radiogroup" aria-label="Vendor approval mode">
                            {(['manual', 'auto'] as const).map((mode) => (
                                <button
                                    key={mode}
                                    type="button"
                                    role="radio"
                                    aria-checked={approvalMode === mode}
                                    disabled={!canConfigureMode}
                                    onClick={() => setMode(mode)}
                                    className={cn(
                                        'rounded px-3 py-1.5 text-sm transition disabled:cursor-not-allowed disabled:opacity-50',
                                        approvalMode === mode ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {mode === 'manual' ? 'Require approval' : 'Auto-approve'}
                                </button>
                            ))}
                        </div>
                        {!canConfigureMode && <span className="text-xs text-muted-foreground">Open from a workspace to change.</span>}
                    </div>
                </div>

                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <nav className="flex flex-wrap gap-1" aria-label="Filter by status">
                        {STATUS_TABS.map((tab) => {
                            const count = tab.value === '' ? total : counts[tab.value];
                            const active = filters.status === tab.value;
                            return (
                                <button
                                    key={tab.label}
                                    type="button"
                                    onClick={() => applyFilter({ status: tab.value })}
                                    aria-pressed={active}
                                    className={cn(
                                        'rounded-md border px-3 py-1.5 text-sm transition',
                                        active ? 'border-primary bg-primary text-primary-foreground' : 'border-input hover:bg-accent',
                                    )}
                                >
                                    {tab.label}
                                    <span className="ms-1.5 tabular-nums opacity-70">{count}</span>
                                </button>
                            );
                        })}
                    </nav>

                    <form onSubmit={submitSearch} className="flex gap-2">
                        <Input
                            type="search"
                            placeholder="Search store or owner email…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full sm:w-72"
                        />
                        <Button type="submit" variant="secondary">
                            Search
                        </Button>
                    </form>
                </div>

                {/* Desktop / tablet: table */}
                <div className="hidden overflow-hidden rounded-lg border bg-card md:block">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Store</th>
                                <th className="px-4 py-3 font-medium">Owner</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Products</th>
                                <th className="px-4 py-3 font-medium">Opened</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {vendors.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-muted-foreground">
                                        No vendors match these filters.
                                    </td>
                                </tr>
                            ) : (
                                vendors.data.map((vendor) => (
                                    <tr key={vendor.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <StoreCell vendor={vendor} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <div>{vendor.owner?.name ?? '—'}</div>
                                            <div className="text-xs text-muted-foreground">{vendor.owner?.email}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={vendor.status} />
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">{vendor.products_count}</td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums">
                                            {new Date(vendor.created_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end">
                                                <RowActions vendor={vendor} onAct={act} onReason={(action) => setReasonFor({ vendor, action })} />
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Mobile: stacked cards */}
                <div className="space-y-3 md:hidden">
                    {vendors.data.length === 0 ? (
                        <div className="rounded-lg border border-dashed bg-card p-8 text-center text-sm text-muted-foreground">
                            No vendors match these filters.
                        </div>
                    ) : (
                        vendors.data.map((vendor) => (
                            <div key={vendor.id} className="rounded-lg border bg-card p-4 shadow-sm">
                                <div className="flex items-start justify-between gap-3">
                                    <StoreCell vendor={vendor} />
                                    <StatusBadge status={vendor.status} />
                                </div>
                                <div className="mt-2 text-xs text-muted-foreground">
                                    {vendor.owner?.email ?? '—'} · {vendor.products_count} products
                                </div>
                                <div className="mt-3 border-t pt-3">
                                    <RowActions vendor={vendor} onAct={act} onReason={(action) => setReasonFor({ vendor, action })} />
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {vendors.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-center gap-1">
                        {vendors.links.map((link, idx) => (
                            <PaginationLink key={idx} link={link} />
                        ))}
                    </nav>
                )}
            </div>

            <ReasonDialog target={reasonFor} onClose={() => setReasonFor(null)} />
        </AppLayout>
    );
}

function StoreCell({ vendor }: { vendor: VendorRow }) {
    return (
        <div className="min-w-0">
            <div className="flex items-center gap-1.5 font-medium">
                <span className="truncate">{vendor.name}</span>
                {vendor.is_verified && <BadgeCheck className="size-4 shrink-0 text-primary" aria-label="Verified" />}
            </div>
            <div className="truncate text-xs text-muted-foreground">
                /{vendor.slug}
                {vendor.company_name && vendor.company_name !== vendor.name && <> · {vendor.company_name}</>}
            </div>
        </div>
    );
}

const STATUS_STYLES: Record<VendorStatus, string> = {
    pending: 'bg-amber-500/15 text-amber-700 hover:bg-amber-500/20 dark:text-amber-400',
    active: 'bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-400',
    suspended: 'bg-destructive/15 text-destructive hover:bg-destructive/20',
    rejected: 'bg-muted text-muted-foreground hover:bg-muted',
};

function StatusBadge({ status }: { status: VendorStatus }) {
    return (
        <Badge variant="secondary" className={cn('capitalize', STATUS_STYLES[status])}>
            {status}
        </Badge>
    );
}

function RowActions({
    vendor,
    onAct,
    onReason,
}: {
    vendor: VendorRow;
    onAct: (vendor: VendorRow, action: 'approve' | 'reinstate' | 'verify') => void;
    onReason: (action: ReasonAction) => void;
}) {
    switch (vendor.status) {
        case 'pending':
            return (
                <div className="flex flex-wrap gap-2">
                    <Button size="sm" onClick={() => onAct(vendor, 'approve')}>
                        Approve
                    </Button>
                    <Button size="sm" variant="outline" onClick={() => onReason('reject')}>
                        Reject
                    </Button>
                </div>
            );
        case 'active':
            return (
                <div className="flex flex-wrap gap-2">
                    {vendor.store_url && (
                        <Button size="sm" variant="ghost" asChild>
                            <a href={vendor.store_url} target="_blank" rel="noreferrer">
                                View
                                <ExternalLink className="ms-1 size-3.5" />
                            </a>
                        </Button>
                    )}
                    {!vendor.is_verified && (
                        <Button size="sm" variant="outline" onClick={() => onAct(vendor, 'verify')}>
                            Verify
                        </Button>
                    )}
                    <Button size="sm" variant="outline" className="text-destructive" onClick={() => onReason('suspend')}>
                        Suspend
                    </Button>
                </div>
            );
        case 'suspended':
            return (
                <Button size="sm" variant="outline" onClick={() => onAct(vendor, 'reinstate')}>
                    Reinstate
                </Button>
            );
        default:
            return <span className="text-xs text-muted-foreground">—</span>;
    }
}

function ReasonDialog({ target, onClose }: { target: { vendor: VendorRow; action: ReasonAction } | null; onClose: () => void }) {
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const close = () => {
        setReason('');
        onClose();
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!target) return;
        router.post(
            route(`admin.vendors.${target.action}`, target.vendor.id),
            { reason },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: close,
            },
        );
    };

    const isSuspend = target?.action === 'suspend';

    return (
        <Dialog open={target !== null} onOpenChange={(open) => !open && close()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {isSuspend ? 'Suspend' : 'Reject'} {target?.vendor.name}
                        </DialogTitle>
                        <DialogDescription>
                            {isSuspend
                                ? 'Their store and products will be hidden from buyers until you reinstate them.'
                                : 'The store will not be listed. This cannot be undone.'}{' '}
                            The owner is notified, with your reason if you give one.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-1.5">
                        <Label htmlFor="moderation-reason">Reason (optional)</Label>
                        <textarea
                            id="moderation-reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows={3}
                            maxLength={500}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder={isSuspend ? 'e.g. Repeated refund complaints' : 'e.g. Store name impersonates another brand'}
                        />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={close}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            {processing ? 'Saving…' : isSuspend ? 'Suspend store' : 'Reject store'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PaginationLink({ link }: { link: { url: string | null; label: string; active: boolean } }) {
    const className = cn(
        'min-w-9 rounded-md border px-3 py-1.5 text-sm transition',
        link.active ? 'border-primary bg-primary text-primary-foreground' : link.url ? 'border-input hover:bg-accent' : 'border-transparent text-muted-foreground',
    );

    if (!link.url) {
        return <span className={className} dangerouslySetInnerHTML={{ __html: link.label }} />;
    }
    return <Link href={link.url} preserveScroll preserveState className={className} dangerouslySetInnerHTML={{ __html: link.label }} />;
}
