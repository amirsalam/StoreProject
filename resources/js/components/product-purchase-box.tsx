import { Button } from '@/components/ui/button';
import LicensePicker, { type LicenseTier } from '@/components/license-picker';
import { useTranslate } from '@/hooks/use-translate';
import { type Product, type ProductType, type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, Info, Loader2, Minus, Plus, ShieldCheck, ShoppingBag } from 'lucide-react';
import { useState } from 'react';

function money(amount: number, currency = 'USD') {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount);
    } catch {
        return `$${amount.toFixed(2)}`;
    }
}

/**
 * The buy box on the product page, marketplace style: license tier, what's
 * included, optional support extension, quantity and Add to cart. Prices
 * mirror CartService::unitPrice() — the server recomputes them anyway.
 */
export default function ProductPurchaseBox({ product }: { product: Product }) {
    const { t } = useTranslate();
    const { branding, name } = usePage<SharedData>().props;

    const currency = product.currency || 'USD';
    const regular = parseFloat(product.sale_price ?? product.price);
    const listPrice = parseFloat(product.price);
    const offered = product.effective_extended_price ?? product.extended_price ?? null;
    const extendedPrice = offered != null ? parseFloat(offered) : null;
    const supportMonths = product.support_months ?? 0;
    const extensionPrice =
        product.support_extension_price != null && supportMonths > 0 && supportMonths < 12 ? parseFloat(product.support_extension_price) : null;
    const quantityLocked = (product.type as ProductType) === 'subscription';

    const [tier, setTier] = useState<LicenseTier>('regular');
    const [extendSupport, setExtendSupport] = useState(false);
    const [quantity, setQuantity] = useState(1);
    const [adding, setAdding] = useState(false);
    const [justAdded, setJustAdded] = useState(false);
    const [cartError, setCartError] = useState<string | null>(null);

    const unit = (tier === 'extended' && extendedPrice !== null ? extendedPrice : regular) + (extendSupport && extensionPrice !== null ? extensionPrice : 0);
    const licensePrice = tier === 'extended' && extendedPrice !== null ? extendedPrice : regular;
    const onSale = tier === 'regular' && product.sale_price !== null && regular < listPrice;
    const seller = product.vendor?.status === 'active' ? product.vendor.name : (branding?.title ?? name);

    const setQty = (value: number) => setQuantity(Math.min(99, Math.max(1, Number.isFinite(value) ? Math.round(value) : 1)));

    const addToCart = () => {
        router.post(
            route('cart.add'),
            {
                product_id: product.id,
                quantity: quantityLocked ? 1 : quantity,
                extended: tier === 'extended',
                extended_support: extendSupport,
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setAdding(true);
                    setCartError(null);
                },
                onError: (errors) => setCartError(errors.cart ?? Object.values(errors)[0] ?? null),
                onFinish: () => setAdding(false),
                onSuccess: () => {
                    setJustAdded(true);
                    setTimeout(() => setJustAdded(false), 2000);
                },
            },
        );
    };

    return (
        <div className="rounded-xl border bg-card p-6 shadow-sm">
            {/* License tier + price */}
            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                {quantityLocked ? (
                    // Subscriptions are billed by plan — no license tiers.
                    <p className="text-base font-semibold">{t('product.types.subscription')}</p>
                ) : (
                    <LicensePicker
                        value={tier}
                        onChange={setTier}
                        regularPrice={money(regular, currency)}
                        extendedPrice={extendedPrice !== null ? money(extendedPrice, currency) : null}
                    />
                )}
                <div className="shrink-0 text-end">
                    <p className="text-3xl font-bold tabular-nums">{money(licensePrice, currency)}</p>
                    {onSale && <p className="text-sm text-muted-foreground line-through">{money(listPrice, currency)}</p>}
                </div>
            </div>

            {/* What's included */}
            <ul className="mt-5 space-y-2 border-t pt-4 text-sm">
                <Included>{t('product.quality_checked', { brand: branding?.title ?? name })}</Included>
                <Included>{t('product.future_updates')}</Included>
                {supportMonths > 0 && (
                    <Included title={t('product.support_help')}>
                        {t('product.support_included', { months: supportMonths, seller })}
                    </Included>
                )}
            </ul>

            {/* Support extension */}
            {extensionPrice !== null && (
                <label className="mt-4 flex cursor-pointer items-center justify-between gap-3 rounded-lg border px-3 py-2.5 text-sm transition-colors hover:bg-muted/40 has-[:checked]:border-primary/60 has-[:checked]:bg-primary/5">
                    <span className="flex items-center gap-2.5">
                        <input
                            type="checkbox"
                            checked={extendSupport}
                            onChange={(e) => setExtendSupport(e.target.checked)}
                            className="size-4 rounded border-input accent-[var(--primary)]"
                        />
                        {t('product.extend_support')}
                    </span>
                    <span className="font-semibold tabular-nums">{money(extensionPrice, currency)}</span>
                </label>
            )}

            {/* Quantity */}
            {!quantityLocked && (
                <div className="mt-4">
                    <p className="mb-1.5 text-sm font-medium">{t('product.quantity')}</p>
                    <div className="flex items-center gap-2">
                        <Button type="button" size="icon" variant="outline" className="rounded-full" onClick={() => setQty(quantity - 1)} disabled={quantity <= 1} aria-label={t('product.decrease')}>
                            <Minus />
                        </Button>
                        <input
                            type="number"
                            min={1}
                            max={99}
                            value={quantity}
                            onChange={(e) => setQty(parseInt(e.target.value, 10))}
                            aria-label={t('product.quantity')}
                            className="h-9 flex-1 rounded-md border border-input bg-background text-center text-sm tabular-nums [appearance:textfield] focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-hidden [&::-webkit-inner-spin-button]:appearance-none"
                        />
                        <Button type="button" size="icon" variant="outline" className="rounded-full" onClick={() => setQty(quantity + 1)} disabled={quantity >= 99} aria-label={t('product.increase')}>
                            <Plus />
                        </Button>
                    </div>
                </div>
            )}

            <Button className="mt-5 w-full" size="lg" onClick={addToCart} disabled={adding}>
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

            {cartError && <p className="mt-2 rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive">{cartError}</p>}

            {(quantity > 1 || extendSupport) && (
                <p className="mt-2 text-center text-sm font-medium tabular-nums">{t('product.total', { amount: money(unit * (quantityLocked ? 1 : quantity), currency) })}</p>
            )}

            <p className="mt-3 text-center text-xs text-muted-foreground italic">{t('product.price_note', { currency })}</p>
            <p className="mt-1 flex items-center justify-center gap-1.5 text-center text-xs text-muted-foreground">
                <ShieldCheck className="size-3.5" />
                {t('product.secure_checkout')}
            </p>
        </div>
    );
}

function Included({ children, title }: { children: React.ReactNode; title?: string }) {
    return (
        <li className="flex items-start gap-2" title={title}>
            <Check className="mt-0.5 size-4 shrink-0 text-emerald-500" />
            <span>{children}</span>
            {title && <Info className="mt-1 size-3 shrink-0 text-muted-foreground" />}
        </li>
    );
}
