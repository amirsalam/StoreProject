import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTranslate } from '@/hooks/use-translate';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { Fragment } from 'react';

export type LicenseTier = 'regular' | 'extended';

/** Render "**bold**" segments of a translated sentence. */
function withBold(text: string) {
    return text.split(/\*\*(.+?)\*\*/g).map((part, i) => (i % 2 === 1 ? <strong key={i} className="text-foreground">{part}</strong> : <Fragment key={i}>{part}</Fragment>));
}

/**
 * Marketplace-style license chooser: the trigger shows the chosen tier;
 * the panel lists both licenses with their price, what each allows, and
 * which one is selected. An Extended License the product doesn't sell is
 * still listed (disabled) so buyers know the option exists.
 */
export default function LicensePicker({
    value,
    onChange,
    regularPrice,
    extendedPrice,
}: {
    value: LicenseTier;
    onChange: (tier: LicenseTier) => void;
    /** Formatted price. */
    regularPrice: string;
    /** Formatted price, or null when not offered. */
    extendedPrice: string | null;
}) {
    const { t } = useTranslate();

    const options: { tier: LicenseTier; name: string; price: string | null; description: string }[] = [
        { tier: 'regular', name: t('product.regular_license'), price: regularPrice, description: t('product.regular_license_desc') },
        { tier: 'extended', name: t('product.extended_license'), price: extendedPrice, description: t('product.extended_license_desc') },
    ];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="group inline-flex items-center gap-1.5 rounded-md text-base font-semibold whitespace-nowrap outline-none focus-visible:ring-2 focus-visible:ring-ring"
                aria-label={t('product.choose_license')}
            >
                {value === 'extended' ? t('product.extended_license') : t('product.regular_license')}
                <ChevronDown className="size-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
            </DropdownMenuTrigger>

            <DropdownMenuContent align="start" sideOffset={8} className="w-[min(22rem,calc(100vw-2rem))] p-0">
                {options.map((option, i) => {
                    const selected = option.tier === value;
                    const available = option.price !== null;

                    return (
                        <Fragment key={option.tier}>
                            {i > 0 && <DropdownMenuSeparator className="my-0" />}
                            <DropdownMenuItem
                                disabled={!available}
                                onSelect={() => available && onChange(option.tier)}
                                className={cn('flex flex-col items-stretch gap-1.5 rounded-none px-4 py-3.5', selected && 'bg-primary/5')}
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <span className="flex items-center gap-2 font-semibold">
                                        {option.name}
                                        {selected && (
                                            <span className="rounded bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">
                                                {t('product.license_selected')}
                                            </span>
                                        )}
                                    </span>
                                    <span className="text-lg font-bold tabular-nums">{option.price ?? '—'}</span>
                                </div>
                                <p className="text-xs leading-relaxed text-muted-foreground">{withBold(option.description)}</p>
                                {!available && <p className="text-xs text-amber-600 dark:text-amber-400">{t('product.license_not_offered')}</p>}
                            </DropdownMenuItem>
                        </Fragment>
                    );
                })}
                <DropdownMenuSeparator className="my-0" />
                <div className="p-2 text-center">
                    <Link href={route('license')} className="text-sm text-primary hover:underline">
                        {t('product.view_license_details')}
                    </Link>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
