import { Button } from '@/components/ui/button';
import { Container } from '@/components/ui/container';
import StorefrontLayout from '@/layouts/storefront-layout';
import { cn } from '@/lib/utils';
import { type ProductType } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Minus, Plus, ShieldCheck, ShoppingBag, Trash2 } from 'lucide-react';

interface CartItem {
    product: {
        id: number;
        title: string;
        slug: string;
        type: ProductType;
        thumbnail: string | null;
        price: string;
        sale_price: string | null;
    };
    quantity: number;
    unit_price: number;
    line_total: number;
}

interface CartIndexProps {
    items: CartItem[];
    subtotal: number;
    currency: string;
}

const TYPE_LABEL: Record<ProductType, string> = {
    digital_download: 'Digital download',
    subscription: 'Subscription',
    api_access: 'API access',
    license: 'License',
};

const QUANTITY_LOCKED: ProductType[] = ['subscription', 'api_access', 'license'];

function money(value: number, currency = 'USD') {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
    } catch {
        return `$${value.toFixed(2)}`;
    }
}

export default function CartIndex({ items, subtotal, currency }: CartIndexProps) {
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;

    const updateQty = (productId: number, quantity: number) => {
        router.patch(
            route('cart.update', productId),
            { quantity },
            { preserveScroll: true, preserveState: true },
        );
    };

    const removeItem = (productId: number) => {
        router.delete(route('cart.destroy', productId), { preserveScroll: true });
    };

    const clearAll = () => {
        if (!confirm('Remove all items from your cart?')) return;
        router.delete(route('cart.clear'), { preserveScroll: true });
    };

    const isEmpty = items.length === 0;

    return (
        <StorefrontLayout>
            <Head title="Your cart" />

            <Container className="py-10 sm:py-16">
                <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">Your cart</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {isEmpty
                                ? 'Your cart is empty.'
                                : `${items.length} ${items.length === 1 ? 'item' : 'items'} ready for checkout.`}
                        </p>
                    </div>
                    {!isEmpty && (
                        <Button variant="ghost" size="sm" onClick={clearAll}>
                            <Trash2 />
                            Clear cart
                        </Button>
                    )}
                </div>

                {flash?.success && (
                    <div className="mb-6 rounded-lg border border-primary/30 bg-primary/[0.06] px-4 py-3 text-sm text-primary">
                        {flash.success}
                    </div>
                )}

                {isEmpty ? (
                    <EmptyCart />
                ) : (
                    <div className="grid gap-8 lg:grid-cols-[1fr_360px]">
                        <ul className="overflow-hidden rounded-xl border bg-card divide-y">
                            {items.map((item) => {
                                const locked = QUANTITY_LOCKED.includes(item.product.type);
                                return (
                                    <li key={item.product.id} className="p-4 sm:p-5">
                                        <div className="flex gap-4">
                                            <Link
                                                href={route('products.show', item.product.slug)}
                                                className="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-muted to-muted/40 text-lg font-semibold text-muted-foreground sm:size-24"
                                            >
                                                {item.product.thumbnail ? (
                                                    <img
                                                        src={item.product.thumbnail}
                                                        alt={item.product.title}
                                                        className="size-full object-cover"
                                                    />
                                                ) : (
                                                    <span>{item.product.title.slice(0, 2).toUpperCase()}</span>
                                                )}
                                            </Link>

                                            <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="min-w-0 space-y-1">
                                                    <Link
                                                        href={route('products.show', item.product.slug)}
                                                        className="block truncate text-sm font-medium hover:underline sm:text-base"
                                                    >
                                                        {item.product.title}
                                                    </Link>
                                                    <p className="font-mono text-[11px] uppercase tracking-wider text-muted-foreground">
                                                        {TYPE_LABEL[item.product.type]}
                                                    </p>
                                                    {item.product.sale_price && (
                                                        <p className="text-xs text-muted-foreground">
                                                            <span className="line-through">
                                                                {money(parseFloat(item.product.price), currency)}
                                                            </span>{' '}
                                                            <span className="ml-1 text-emerald-600 dark:text-emerald-400">
                                                                On sale
                                                            </span>
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="flex items-center gap-3 sm:flex-col sm:items-end sm:gap-2">
                                                    <QuantityStepper
                                                        value={item.quantity}
                                                        locked={locked}
                                                        onChange={(q) => updateQty(item.product.id, q)}
                                                    />
                                                    <div className="text-right">
                                                        <div className="font-display text-base font-semibold tabular-nums">
                                                            {money(item.line_total, currency)}
                                                        </div>
                                                        {item.quantity > 1 && (
                                                            <div className="text-[11px] text-muted-foreground tabular-nums">
                                                                {money(item.unit_price, currency)} ea
                                                            </div>
                                                        )}
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeItem(item.product.id)}
                                                        className="inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-destructive"
                                                    >
                                                        <Trash2 className="size-3" />
                                                        Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>

                        <OrderSummary subtotal={subtotal} currency={currency} />
                    </div>
                )}
            </Container>
        </StorefrontLayout>
    );
}

function QuantityStepper({
    value,
    locked,
    onChange,
}: {
    value: number;
    locked: boolean;
    onChange: (q: number) => void;
}) {
    if (locked) {
        return (
            <span className="inline-flex h-9 items-center justify-center rounded-md border border-border bg-muted/40 px-3 font-mono text-[11px] uppercase tracking-wider text-muted-foreground">
                Qty 1
            </span>
        );
    }
    return (
        <div className="inline-flex h-9 items-center rounded-md border border-border bg-background">
            <button
                type="button"
                aria-label="Decrease quantity"
                onClick={() => onChange(Math.max(1, value - 1))}
                disabled={value <= 1}
                className={cn(
                    'flex h-full w-8 items-center justify-center rounded-l-md text-muted-foreground transition-colors',
                    value > 1 ? 'hover:bg-muted hover:text-foreground' : 'opacity-50',
                )}
            >
                <Minus className="size-3.5" />
            </button>
            <span className="flex h-full w-8 items-center justify-center font-mono text-sm tabular-nums">{value}</span>
            <button
                type="button"
                aria-label="Increase quantity"
                onClick={() => onChange(value + 1)}
                disabled={value >= 99}
                className={cn(
                    'flex h-full w-8 items-center justify-center rounded-r-md text-muted-foreground transition-colors',
                    value < 99 ? 'hover:bg-muted hover:text-foreground' : 'opacity-50',
                )}
            >
                <Plus className="size-3.5" />
            </button>
        </div>
    );
}

function OrderSummary({ subtotal, currency }: { subtotal: number; currency: string }) {
    const tax = 0;
    const total = subtotal + tax;
    return (
        <aside className="space-y-4">
            <div className="sticky top-24 space-y-4 rounded-xl border bg-card p-6 shadow-sm">
                <h2 className="font-display text-base font-semibold tracking-tight">Order summary</h2>
                <dl className="space-y-2 text-sm">
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">Subtotal</dt>
                        <dd className="tabular-nums">{money(subtotal, currency)}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">Tax</dt>
                        <dd className="tabular-nums text-muted-foreground">Calculated at checkout</dd>
                    </div>
                </dl>
                <div className="flex items-baseline justify-between border-t pt-4">
                    <span className="font-display text-sm font-semibold">Total</span>
                    <span className="font-display text-2xl font-semibold tabular-nums">
                        {money(total, currency)}
                    </span>
                </div>
                <Button size="lg" className="w-full" disabled>
                    Continue to checkout
                    <ArrowRight />
                </Button>
                <p className="flex items-center justify-center gap-1.5 text-center text-[11px] text-muted-foreground">
                    <ShieldCheck className="size-3" />
                    Stripe checkout — coming soon
                </p>
            </div>

            <div className="rounded-xl border border-border/60 bg-muted/30 p-4 text-xs text-muted-foreground">
                Need help? Email{' '}
                <a href="mailto:support@storeproject.test" className="font-medium text-foreground hover:underline">
                    support@storeproject.test
                </a>{' '}
                — we reply within an hour during business days.
            </div>
        </aside>
    );
}

function EmptyCart() {
    return (
        <div className="rounded-xl border border-dashed border-border/80 bg-muted/20 px-6 py-20 text-center">
            <div className="mx-auto mb-5 inline-flex size-12 items-center justify-center rounded-full border border-border/80 bg-background text-muted-foreground">
                <ShoppingBag className="size-5" />
            </div>
            <h2 className="font-display text-lg font-semibold tracking-tight">Your cart is empty</h2>
            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                Add a script, template, or license to get started. You can mix subscriptions and one-time products
                in the same order.
            </p>
            <Button asChild className="mt-6">
                <Link href={route('products.index')}>
                    Browse products
                    <ArrowRight />
                </Link>
            </Button>
        </div>
    );
}
