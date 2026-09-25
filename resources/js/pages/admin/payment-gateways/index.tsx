import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated, type PaymentGatewaySummary } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Power, Star, TestTube2, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Option {
    value: string;
    label: string;
}

interface Filters {
    search: string;
    status: string;
    environment: string;
}

interface Props {
    gateways: Paginated<PaymentGatewaySummary>;
    filters: Filters;
    providers: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/payment-gateways' },
    { title: 'Payment Gateways', href: '/admin/payment-gateways' },
];

export default function PaymentGatewaysIndex({ gateways, filters }: Props) {
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const [search, setSearch] = useState(filters.search);
    const { delete: destroy, post, processing } = useForm({});

    const applyFilter = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries(merged)) {
            if (v) params[k] = String(v);
        }
        router.get(route('admin.payment-gateways.index'), params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    const handleDelete = (g: PaymentGatewaySummary) => {
        if (!confirm(`Delete "${g.display_name}"? This cannot be undone.`)) return;
        destroy(route('admin.payment-gateways.destroy', g.id), { preserveScroll: true });
    };

    const toggle = (g: PaymentGatewaySummary) =>
        post(route('admin.payment-gateways.toggle', g.id), { preserveScroll: true });

    const makeDefault = (g: PaymentGatewaySummary) =>
        post(route('admin.payment-gateways.default', g.id), { preserveScroll: true });

    const test = (g: PaymentGatewaySummary) =>
        post(route('admin.payment-gateways.test', g.id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin · Payment Gateways" />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                        {flash.error}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Payment Gateways</h1>
                        <p className="text-sm text-muted-foreground">
                            {gateways.total} {gateways.total === 1 ? 'gateway' : 'gateways'} configured
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.payment-gateways.create')}>
                            <Plus className="mr-1" /> New gateway
                        </Link>
                    </Button>
                </div>

                <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <Input
                        type="search"
                        placeholder="Search by name or provider…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full sm:w-64"
                    />
                    <select
                        value={filters.status}
                        onChange={(e) => applyFilter({ status: e.target.value })}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select
                        value={filters.environment}
                        onChange={(e) => applyFilter({ environment: e.target.value })}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">All environments</option>
                        <option value="sandbox">Sandbox</option>
                        <option value="production">Production</option>
                    </select>
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                </form>

                {/* Desktop table */}
                <div className="hidden overflow-hidden rounded-lg border bg-card md:block">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Gateway</th>
                                <th className="px-4 py-3 font-medium">Environment</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Fees</th>
                                <th className="px-4 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {gateways.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-12 text-center text-muted-foreground">
                                        No gateways yet. Add your first payment provider.
                                    </td>
                                </tr>
                            ) : (
                                gateways.data.map((g) => (
                                    <tr key={g.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2 font-medium">
                                                {g.logo && <span aria-hidden>{g.logo}</span>}
                                                {g.display_name}
                                                {g.is_default && (
                                                    <Badge variant="default" className="gap-1">
                                                        <Star className="size-3" /> Default
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="text-xs text-muted-foreground">{g.provider_label}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <EnvBadge env={g.environment} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge active={g.is_active} />
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-muted-foreground">
                                            {g.fee_percent}% + ${g.fee_fixed}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <IconButton title="Test connection" onClick={() => test(g)} disabled={processing}>
                                                    <TestTube2 />
                                                </IconButton>
                                                {!g.is_default && (
                                                    <IconButton title="Set as default" onClick={() => makeDefault(g)} disabled={processing}>
                                                        <Star />
                                                    </IconButton>
                                                )}
                                                <IconButton
                                                    title={g.is_active ? 'Disable' : 'Enable'}
                                                    onClick={() => toggle(g)}
                                                    disabled={processing}
                                                    className={g.is_active ? 'text-emerald-600' : ''}
                                                >
                                                    <Power />
                                                </IconButton>
                                                <Button asChild size="sm" variant="ghost">
                                                    <Link href={route('admin.payment-gateways.edit', g.id)}>
                                                        <Pencil />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => handleDelete(g)}
                                                    disabled={processing}
                                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Mobile cards */}
                <div className="space-y-3 md:hidden">
                    {gateways.data.length === 0 ? (
                        <div className="rounded-lg border border-dashed bg-card p-8 text-center text-sm text-muted-foreground">
                            No gateways yet. Add your first payment provider.
                        </div>
                    ) : (
                        gateways.data.map((g) => (
                            <div key={g.id} className="rounded-lg border bg-card p-4 shadow-sm">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2 font-medium">
                                            {g.logo && <span aria-hidden>{g.logo}</span>}
                                            <span className="truncate">{g.display_name}</span>
                                        </div>
                                        <div className="text-xs text-muted-foreground">{g.provider_label}</div>
                                    </div>
                                    <StatusBadge active={g.is_active} />
                                </div>
                                <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                    <EnvBadge env={g.environment} />
                                    {g.is_default && (
                                        <Badge variant="default" className="gap-1">
                                            <Star className="size-3" /> Default
                                        </Badge>
                                    )}
                                    <span className="text-muted-foreground">
                                        {g.fee_percent}% + ${g.fee_fixed}
                                    </span>
                                </div>
                                <div className="mt-3 flex flex-wrap items-center justify-end gap-1 border-t pt-3">
                                    <Button size="sm" variant="ghost" onClick={() => test(g)} disabled={processing} className="h-9">
                                        <TestTube2 /> <span className="ms-1">Test</span>
                                    </Button>
                                    {!g.is_default && (
                                        <Button size="sm" variant="ghost" onClick={() => makeDefault(g)} disabled={processing} className="h-9">
                                            <Star /> <span className="ms-1">Default</span>
                                        </Button>
                                    )}
                                    <Button size="sm" variant="ghost" onClick={() => toggle(g)} disabled={processing} className="h-9">
                                        <Power /> <span className="ms-1">{g.is_active ? 'Disable' : 'Enable'}</span>
                                    </Button>
                                    <Button asChild size="sm" variant="ghost" className="h-9">
                                        <Link href={route('admin.payment-gateways.edit', g.id)}>
                                            <Pencil /> <span className="ms-1">Edit</span>
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => handleDelete(g)}
                                        disabled={processing}
                                        className="h-9 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    >
                                        <Trash2 /> <span className="ms-1">Delete</span>
                                    </Button>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {gateways.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-center gap-1">
                        {gateways.links.map((link, idx) => (
                            <PaginationLink key={idx} link={link} />
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}

function IconButton({
    children,
    title,
    onClick,
    disabled,
    className = '',
}: {
    children: React.ReactNode;
    title: string;
    onClick: () => void;
    disabled?: boolean;
    className?: string;
}) {
    return (
        <Button size="sm" variant="ghost" title={title} aria-label={title} onClick={onClick} disabled={disabled} className={className}>
            {children}
        </Button>
    );
}

function StatusBadge({ active }: { active: boolean }) {
    return (
        <Badge variant={active ? 'default' : 'secondary'}>{active ? 'Active' : 'Inactive'}</Badge>
    );
}

function EnvBadge({ env }: { env: string }) {
    return (
        <Badge variant={env === 'production' ? 'default' : 'outline'} className="capitalize">
            {env === 'production' ? 'Live' : 'Sandbox'}
        </Badge>
    );
}

function PaginationLink({ link }: { link: { url: string | null; label: string; active: boolean } }) {
    const className = `min-w-9 rounded-md border px-3 py-1.5 text-sm transition ${
        link.active
            ? 'border-primary bg-primary text-primary-foreground'
            : link.url
                ? 'border-input hover:bg-accent'
                : 'border-transparent text-muted-foreground'
    }`;

    if (!link.url) {
        return <span className={className} dangerouslySetInnerHTML={{ __html: link.label }} />;
    }

    return (
        <Link
            href={link.url}
            preserveScroll
            preserveState
            className={className}
            dangerouslySetInnerHTML={{ __html: link.label }}
        />
    );
}
