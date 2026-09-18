import ProductCard from '@/components/product-card';
import { Button } from '@/components/ui/button';
import { Container } from '@/components/ui/container';
import { Input } from '@/components/ui/input';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type Category, type Paginated, type Product, type ProductType } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Filters {
    search: string;
    category: string;
    type: ProductType | '';
    sort: string;
}

interface ProductsIndexProps {
    products: Paginated<Product>;
    categories: Category[];
    types: { value: ProductType; label: string }[];
    filters: Filters;
}

const SORT_OPTIONS = [
    { value: 'latest', label: 'Latest' },
    { value: 'price_asc', label: 'Price: low to high' },
    { value: 'price_desc', label: 'Price: high to low' },
    { value: 'bestseller', label: 'Best sellers' },
];

export default function ProductsIndex({ products, categories, types, filters }: ProductsIndexProps) {
    const [search, setSearch] = useState(filters.search);

    const applyFilter = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries(merged)) {
            if (v) params[k] = String(v);
        }
        router.get(route('products.index'), params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    const clearFilters = () => {
        setSearch('');
        router.get(route('products.index'), {}, { preserveScroll: true, preserveState: true, replace: true });
    };

    const hasActiveFilters = Boolean(filters.search || filters.category || filters.type || filters.sort);
    const rootCategories = categories.filter((c) => c.parent_id === null);

    return (
        <StorefrontLayout>
            <Head title="Products" />

            <Container className="py-8 sm:py-12">
                <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight sm:text-3xl">Browse products</h1>
                        <p className="text-sm text-muted-foreground">
                            {products.total} {products.total === 1 ? 'product' : 'products'} available
                        </p>
                    </div>
                    <form onSubmit={submitSearch} className="flex w-full gap-2 sm:w-auto">
                        <Input
                            type="search"
                            placeholder="Search products..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full sm:w-64"
                        />
                        <Button type="submit">Search</Button>
                    </form>
                </div>

                <div className="grid gap-8 md:grid-cols-[220px_1fr] xl:grid-cols-[240px_1fr]">
                <aside className="space-y-6 text-sm">
                    <FilterSection title="Type">
                        {types.map((t) => (
                            <button
                                key={t.value}
                                type="button"
                                onClick={() => applyFilter({ type: filters.type === t.value ? '' : t.value })}
                                className={`w-full rounded-md px-2 py-1 text-left transition ${
                                    filters.type === t.value
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-accent hover:text-accent-foreground'
                                }`}
                            >
                                {t.label}
                            </button>
                        ))}
                    </FilterSection>

                    <FilterSection title="Category">
                        {rootCategories.map((cat) => (
                            <button
                                key={cat.id}
                                type="button"
                                onClick={() => applyFilter({ category: filters.category === cat.slug ? '' : cat.slug })}
                                className={`w-full rounded-md px-2 py-1 text-left transition ${
                                    filters.category === cat.slug
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-accent hover:text-accent-foreground'
                                }`}
                            >
                                {cat.name}
                            </button>
                        ))}
                    </FilterSection>

                    {hasActiveFilters && (
                        <Button variant="ghost" size="sm" onClick={clearFilters} className="w-full">
                            Clear filters
                        </Button>
                    )}
                </aside>

                <section>
                    <div className="mb-4 flex justify-end">
                        <select
                            value={filters.sort || 'latest'}
                            onChange={(e) => applyFilter({ sort: e.target.value === 'latest' ? '' : e.target.value })}
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            {SORT_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </div>

                    {products.data.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-12 text-center text-muted-foreground">
                            No products match these filters.
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                            {products.data.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                    )}

                    {products.last_page > 1 && (
                        <nav className="mt-8 flex flex-wrap items-center justify-center gap-1">
                            {products.links.map((link, idx) => (
                                <PaginationLink key={idx} link={link} />
                            ))}
                        </nav>
                    )}
                </section>
            </div>
            </Container>
        </StorefrontLayout>
    );
}

function FilterSection({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="space-y-2">
            <h3 className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{title}</h3>
            <div className="space-y-1">{children}</div>
        </div>
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
