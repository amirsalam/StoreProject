import { useConfirmDialog } from '@/components/confirm-dialog';
import PaginationLinks from '@/components/pagination-links';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ExternalLink, FileArchive, Pencil, Plus, Store, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Option {
    value: string;
    label: string;
}

interface SellerProduct {
    id: number;
    title: string;
    slug: string;
    type: string;
    status: string;
    price: string;
    sale_price: string | null;
    currency: string;
    sales_count: number;
    category: string | null;
    has_file: boolean;
    url: string | null;
}

interface Props {
    vendor: { id: number; name: string; slug: string; status: string } | null;
    products: Paginated<SellerProduct> | null;
    filters: { search: string; status: string };
    statuses: Option[];
    types: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/workspace/products' },
    { title: 'My products', href: '/workspace/products' },
];

function money(amount: string, currency = 'USD'): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(amount));
}

export default function SellerProductsIndex({ vendor, products, filters, statuses, types }: Props) {
    const { __ } = useTranslate();
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const [search, setSearch] = useState(filters.search);
    const { ask, confirmDialog } = useConfirmDialog();

    const applyFilter = (next: Partial<Props['filters']>) => {
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries({ ...filters, ...next })) {
            if (v) params[k] = String(v);
        }
        router.get(route('workspace.products.index'), params, { preserveScroll: true, preserveState: true, replace: true });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    const destroy = (product: SellerProduct) =>
        ask({
            title: __('Delete this product?'),
            description: __(':title will be permanently deleted. If it has already been sold, it is archived instead — hidden from the store, and buyers keep their access.', {
                title: product.title,
            }),
            confirmLabel: __('Delete'),
            destructive: true,
            action: (finish) => router.delete(route('workspace.products.destroy', product.id), { onFinish: finish }),
        });

    const typeLabel = (value: string) => types.find((t) => t.value === value)?.label ?? value;
    const statusLabel = (value: string) => statuses.find((s) => s.value === value)?.label ?? value;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('My products')} />

            <div className="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{__('My products')}</h1>
                        <p className="text-sm text-muted-foreground">
                            {vendor
                                ? __('The products you sell in :store. Buyers get the file and license key automatically after paying.', { store: vendor.name })
                                : __('Open your store to start selling your own digital products.')}
                        </p>
                    </div>
                    {vendor && (
                        <Button asChild>
                            <Link href={route('workspace.products.create')}>
                                <Plus /> {__('New product')}
                            </Link>
                        </Button>
                    )}
                </div>

                {!vendor || !products ? (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed bg-card p-10 text-center">
                        <Store className="size-8 text-muted-foreground" />
                        <p className="max-w-md text-sm text-muted-foreground">
                            {__('You don’t have a store yet. Open one — it takes a minute — then add your products here.')}
                        </p>
                        <Button asChild>
                            <Link href={route('workspace.vendor.edit')}>{__('Open your store')}</Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <Input
                                type="search"
                                placeholder={__('Search by title or slug…')}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full sm:w-64"
                            />
                            <select
                                value={filters.status}
                                onChange={(e) => applyFilter({ status: e.target.value })}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm sm:w-auto"
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

                        {products.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed bg-card p-10 text-center">
                                <p className="text-sm text-muted-foreground">{__('No products yet. Add your first product to start selling.')}</p>
                                <Button asChild>
                                    <Link href={route('workspace.products.create')}>
                                        <Plus /> {__('New product')}
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border bg-card">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-xs tracking-wider text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3 text-start font-medium">{__('Title')}</th>
                                            <th className="px-4 py-3 text-start font-medium">{__('Type')}</th>
                                            <th className="px-4 py-3 text-start font-medium">{__('Status')}</th>
                                            <th className="px-4 py-3 text-start font-medium">{__('Price')}</th>
                                            <th className="px-4 py-3 text-start font-medium">{__('Sales')}</th>
                                            <th className="px-4 py-3 text-end font-medium">{__('Actions')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {products.data.map((p) => (
                                            <tr key={p.id} className="hover:bg-muted/30">
                                                <td className="px-4 py-3">
                                                    <div className="font-medium">{p.title}</div>
                                                    <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        {p.has_file ? (
                                                            <>
                                                                <FileArchive className="size-3" /> {__('File uploaded')}
                                                            </>
                                                        ) : (
                                                            <span className="text-amber-700 dark:text-amber-400">{__('No file uploaded')}</span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">{typeLabel(p.type)}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={p.status === 'published' ? 'default' : 'secondary'}>{statusLabel(p.status)}</Badge>
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {money(p.sale_price ?? p.price, p.currency)}
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">{p.sales_count}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        {p.url && (
                                                            <Button asChild size="sm" variant="ghost" title={__('View on site')}>
                                                                <a href={p.url} target="_blank" rel="noopener noreferrer">
                                                                    <ExternalLink />
                                                                </a>
                                                            </Button>
                                                        )}
                                                        <Button asChild size="sm" variant="ghost" title={__('Edit')}>
                                                            <Link href={route('workspace.products.edit', p.id)}>
                                                                <Pencil />
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            title={__('Delete')}
                                                            onClick={() => destroy(p)}
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

                        {products.last_page > 1 && <PaginationLinks links={products.links} />}
                    </>
                )}
            </div>

            {confirmDialog}
        </AppLayout>
    );
}
