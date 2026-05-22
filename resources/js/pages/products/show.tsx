import ProductCard from '@/components/product-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Container } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type Product, type ProductType, type Review } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Check, Loader2, ShieldCheck, ShoppingBag, Star } from 'lucide-react';
import { useState } from 'react';

interface ProductShowProps {
    product: Product;
    reviews: Review[];
    relatedProducts: Product[];
    averageRating: number;
    reviewsCount: number;
}

function formatPrice(amount: string | null | undefined, currency = 'USD') {
    if (amount == null) return null;
    const value = parseFloat(amount);
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
    } catch {
        return `$${value.toFixed(2)}`;
    }
}

export default function ProductShow({ product, reviews, relatedProducts, averageRating, reviewsCount }: ProductShowProps) {
    const { t } = useTranslate();
    const onSale = product.sale_price !== null && parseFloat(product.sale_price ?? '0') < parseFloat(product.price);
    const price = formatPrice(product.sale_price ?? product.price, product.currency);
    const oldPrice = onSale ? formatPrice(product.price, product.currency) : null;

    const [adding, setAdding] = useState(false);
    const [justAdded, setJustAdded] = useState(false);

    const typeLabel = (type: ProductType) => t(`product.types.${type}`);

    const handleAddToCart = () => {
        router.post(
            route('cart.add'),
            { product_id: product.id, quantity: 1 },
            {
                preserveScroll: true,
                onStart: () => setAdding(true),
                onFinish: () => setAdding(false),
                onSuccess: () => {
                    setJustAdded(true);
                    setTimeout(() => setJustAdded(false), 2000);
                },
            },
        );
    };

    return (
        <StorefrontLayout>
            <Head title={product.title} />

            <Container className="py-8 sm:py-12">
                <nav className="mb-6 text-sm text-muted-foreground">
                <Link href={route('products.index')} className="hover:underline">{t('nav.products')}</Link>
                {product.category && (
                    <>
                        <span className="mx-2">/</span>
                        <Link
                            href={route('products.index', { category: product.category.slug })}
                            className="hover:underline"
                        >
                            {product.category.name}
                        </Link>
                    </>
                )}
            </nav>

            <div className="grid gap-8 lg:grid-cols-[1fr_360px]">
                <div className="space-y-6">
                    <div className="aspect-video w-full overflow-hidden rounded-xl bg-gradient-to-br from-muted to-muted/40 flex items-center justify-center text-2xl text-muted-foreground">
                        {product.thumbnail ? (
                            <img src={product.thumbnail} alt={product.title} className="size-full object-cover" />
                        ) : (
                            <span className="opacity-60">{product.title.slice(0, 2).toUpperCase()}</span>
                        )}
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Badge variant="secondary">{typeLabel(product.type)}</Badge>
                            {product.version && <span className="text-xs text-muted-foreground">v{product.version}</span>}
                        </div>
                        <h1 className="text-3xl font-semibold tracking-tight">{product.title}</h1>
                        {reviewsCount > 0 && (
                            <div className="flex items-center gap-2 text-sm">
                                <RatingStars value={averageRating} />
                                <span className="text-muted-foreground">{averageRating} ({reviewsCount} {t('product.reviews').toLowerCase()})</span>
                            </div>
                        )}
                        {product.short_description && (
                            <p className="text-muted-foreground">{product.short_description}</p>
                        )}
                    </div>

                    {product.description && (
                        <div className="prose prose-sm max-w-none dark:prose-invert">
                            {product.description.split('\n\n').map((para, i) => (
                                <p key={i}>{para}</p>
                            ))}
                        </div>
                    )}

                    <section className="space-y-4 pt-4 border-t">
                        <h2 className="text-xl font-semibold">{t('product.reviews')}</h2>
                        {reviews.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('product.no_reviews')}</p>
                        ) : (
                            <ul className="space-y-4">
                                {reviews.map((review) => (
                                    <li key={review.id} className="rounded-lg border bg-card p-4">
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-sm font-medium">{review.user?.name ?? '—'}</span>
                                            <RatingStars value={review.rating} />
                                        </div>
                                        {review.title && <p className="font-medium mb-1">{review.title}</p>}
                                        {review.comment && <p className="text-sm text-muted-foreground">{review.comment}</p>}
                                        {review.is_verified_purchase && (
                                            <Badge variant="outline" className="mt-2 text-xs">{t('product.verified_purchase')}</Badge>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                <aside>
                    <div className="sticky top-24 rounded-xl border bg-card p-6 shadow-sm">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-bold">{price}</span>
                            {oldPrice && <span className="text-muted-foreground line-through">{oldPrice}</span>}
                        </div>
                        {product.license_type && (
                            <p className="mt-1 text-xs text-muted-foreground">{t('product.license_label', { type: product.license_type })}</p>
                        )}
                        <Button
                            className="mt-4 w-full"
                            size="lg"
                            onClick={handleAddToCart}
                            disabled={adding}
                        >
                            {adding ? (
                                <>
                                    <Loader2 className="animate-spin" />
                                    {t('product.adding')}
                                </>
                            ) : justAdded ? (
                                <>
                                    <Check />
                                    {t('product.added')}
                                </>
                            ) : (
                                <>
                                    <ShoppingBag />
                                    {t('product.add_to_cart')}
                                </>
                            )}
                        </Button>
                        <p className="mt-2 flex items-center justify-center gap-1.5 text-center text-xs text-muted-foreground">
                            <ShieldCheck className="size-3.5" />
                            {t('product.secure_checkout')}
                        </p>

                        <dl className="mt-6 space-y-2 text-sm border-t pt-4">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">{t('product.type')}</dt>
                                <dd>{typeLabel(product.type)}</dd>
                            </div>
                            {product.version && (
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">{t('product.version')}</dt>
                                    <dd>{product.version}</dd>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">{t('product.sales')}</dt>
                                <dd>{product.sales_count}</dd>
                            </div>
                        </dl>
                    </div>
                </aside>
            </div>

            {relatedProducts.length > 0 && (
                <section className="mt-12 space-y-4">
                    <h2 className="text-xl font-semibold">{t('product.related')}</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {relatedProducts.map((p) => (
                            <ProductCard key={p.id} product={p} />
                        ))}
                    </div>
                </section>
            )}
            </Container>
        </StorefrontLayout>
    );
}

function RatingStars({ value }: { value: number }) {
    return (
        <div className="flex">
            {[1, 2, 3, 4, 5].map((i) => (
                <Star
                    key={i}
                    className={`size-4 ${i <= Math.round(value) ? 'fill-yellow-400 text-yellow-400' : 'text-muted-foreground'}`}
                />
            ))}
        </div>
    );
}
