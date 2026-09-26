import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated, type Product, type ProductType } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import ConfirmDialog from '@/components/confirm-dialog';
import { FormEvent, useState } from 'react';

interface Option {
    value: string;
    label: string;
}

interface Filters {
    search: string;
    status: string;
    type: ProductType | '';
}

interface AdminProductsIndexProps {
    products: Paginated<Product & { status: string; updated_at: string }>;
    filters: Filters;
    statuses: Option[];
    types: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Products', href: '/admin/products' },
];

export default function AdminProductsIndex({ products, filters, statuses, types }: AdminProductsIndexProps) {
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const [search, setSearch] = useState(filters.search);
    const { delete: destroy, processing } = useForm({});

    const applyFilter = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries(merged)) {
            if (v) params[k] = String(v);
        }
        router.get(route('admin.products.index'), params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    // The product awaiting delete confirmation in the popup, if any.
    const [pendingDelete, setPendingDelete] = useState<Product | null>(null);

    const handleDelete = (product: Product) => setPendingDelete(product);

    const confirmDelete = () => {
        if (!pendingDelete) return;
        // No preserveScroll: the result message is shown at the top of the page.
        destroy(route('admin.products.destroy', pendingDelete.id), {
            onFinish: () => setPendingDelete(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin · Products" />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Products</h1>
                        <p className="text-sm text-muted-foreground">
                            {products.total} {products.total === 1 ? 'product' : 'products'} total
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.products.create')}>
                            <Plus className="mr-1" /> New product
                        </Link>
                    </Button>
                </div>

                <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <Input
                        type="search"
                        placeholder="Search by title or slug…"
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
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                    <select
                        value={filters.type}
                        onChange={(e) => applyFilter({ type: e.target.value as ProductType | '' })}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">All types</option>
                        {types.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                </form>

                {/* Desktop / tablet: data table */}
                <div className="hidden overflow-hidden rounded-lg border bg-card md:block">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Title</th>
                                <th className="px-4 py-3 font-medium">Type</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Price</th>
                                <th className="px-4 py-3 font-medium">Sales</th>
                                <th className="px-4 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {products.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-muted-foreground">
                                        No products match these filters.
                                    </td>
                                </tr>
                            ) : (
                                products.data.map((product) => (
                                    <tr key={product.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <div className="font-medium">{product.title}</div>
                                            <div className="text-xs text-muted-foreground">{product.slug}</div>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {typeLabel(product.type, types)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={product.status} />
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {product.sale_price ? (
                                                <span>
                                                    <span className="font-medium">${product.sale_price}</span>
                                                    <span className="ml-1 text-xs text-muted-foreground line-through">
                                                        ${product.price}
                                                    </span>
                                                </span>
                                            ) : (
                                                <span>${product.price}</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-muted-foreground">
                                            {product.sales_count}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button asChild size="sm" variant="ghost">
                                                    <Link href={route('admin.products.edit', product.id)}>
                                                        <Pencil />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => handleDelete(product)}
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

                {/* Mobile: stacked card list */}
                <div className="space-y-3 md:hidden">
                    {products.data.length === 0 ? (
                        <div className="rounded-lg border border-dashed bg-card p-8 text-center text-sm text-muted-foreground">
                            No products match these filters.
                        </div>
                    ) : (
                        products.data.map((product) => (
                            <div
                                key={product.id}
                                className="rounded-lg border bg-card p-4 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">{product.title}</div>
                                        <div className="truncate font-mono text-[11px] text-muted-foreground">
                                            {product.slug}
                                        </div>
                                    </div>
                                    <StatusBadge status={product.status} />
                                </div>

                                <dl className="mt-3 grid grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <dt className="text-muted-foreground">Type</dt>
                                        <dd className="mt-0.5 truncate">{typeLabel(product.type, types)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Price</dt>
                                        <dd className="mt-0.5 tabular-nums">
                                            {product.sale_price ? (
                                                <>
                                                    <span className="font-medium">${product.sale_price}</span>
                                                    <span className="ms-1 text-[10px] text-muted-foreground line-through">
                                                        ${product.price}
                                                    </span>
                                                </>
                                            ) : (
                                                <span>${product.price}</span>
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Sales</dt>
                                        <dd className="mt-0.5 tabular-nums">{product.sales_count}</dd>
                                    </div>
                                </dl>

                                <div className="mt-3 flex items-center justify-end gap-1 border-t pt-3">
                                    <Button asChild size="sm" variant="ghost" className="h-9">
                                        <Link href={route('admin.products.edit', product.id)}>
                                            <Pencil />
                                            <span className="ms-1">Edit</span>
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => handleDelete(product)}
                                        disabled={processing}
                                        className="h-9 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    >
                                        <Trash2 />
                                        <span className="ms-1">Delete</span>
                                    </Button>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {products.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-center gap-1">
                        {products.links.map((link, idx) => (
                            <PaginationLink key={idx} link={link} />
                        ))}
                    </nav>
                )}
            </div>

            <ConfirmDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => !open && setPendingDelete(null)}
                title="Delete this product?"
                description={
                    <>
                        <strong className="text-foreground">{pendingDelete?.title}</strong> will be permanently deleted. If it has already been
                        sold, it is archived instead — hidden from the store, and buyers keep their access.
                    </>
                }
                confirmLabel="Delete"
                destructive
                processing={processing}
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}

function typeLabel(value: string, types: Option[]): string {
    return types.find((t) => t.value === value)?.label ?? value;
}

function StatusBadge({ status }: { status: string }) {
    const variant: Record<string, 'default' | 'secondary' | 'outline'> = {
        published: 'default',
        draft: 'secondary',
        archived: 'outline',
    };
    return (
        <Badge variant={variant[status] ?? 'secondary'} className="capitalize">
            {status}
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
