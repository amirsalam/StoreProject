import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { type Product, type ProductType } from '@/types';
import { Link } from '@inertiajs/react';

const TYPE_LABEL: Record<ProductType, string> = {
    digital_download: 'Download',
    subscription: 'Subscription',
    api_access: 'API',
    license: 'License',
};

function formatPrice(amount: string | null | undefined, currency = 'USD') {
    if (amount == null) return null;
    const value = typeof amount === 'string' ? parseFloat(amount) : amount;
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
    } catch {
        return `$${value.toFixed(2)}`;
    }
}

export default function ProductCard({ product }: { product: Product }) {
    const onSale = product.sale_price !== null && parseFloat(product.sale_price ?? '0') < parseFloat(product.price);
    const price = formatPrice(product.sale_price ?? product.price, product.currency);
    const oldPrice = onSale ? formatPrice(product.price, product.currency) : null;

    return (
        <Link
            href={route('products.show', product.slug)}
            className="group block focus:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-lg"
        >
            <Card className="h-full overflow-hidden transition-shadow group-hover:shadow-md">
                <div className="aspect-[4/3] w-full bg-gradient-to-br from-muted to-muted/40 flex items-center justify-center text-muted-foreground text-sm">
                    {product.thumbnail ? (
                        <img src={product.thumbnail} alt={product.title} className="size-full object-cover" />
                    ) : (
                        <span className="opacity-60">{product.title.slice(0, 2).toUpperCase()}</span>
                    )}
                </div>
                <CardContent className="space-y-2 p-4">
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary" className="text-xs">{TYPE_LABEL[product.type]}</Badge>
                        {product.category?.name && (
                            <span className="text-xs text-muted-foreground truncate">{product.category.name}</span>
                        )}
                    </div>
                    <h3 className="line-clamp-2 font-medium leading-tight">{product.title}</h3>
                    {product.short_description && (
                        <p className="line-clamp-2 text-sm text-muted-foreground">{product.short_description}</p>
                    )}
                    <div className="flex items-baseline gap-2 pt-1">
                        <span className="font-semibold">{price}</span>
                        {oldPrice && <span className="text-sm text-muted-foreground line-through">{oldPrice}</span>}
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}
