import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTranslate } from '@/hooks/use-translate';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

/** Shown first: the currencies most stores pick. */
const POPULAR = ['USD', 'EUR', 'GBP', 'MAD', 'SAR', 'AED', 'CAD', 'AUD', 'JPY', 'CHF'];

interface Option {
    code: string;
    name: string;
    symbol: string;
}

/** Currency names and symbols in the visitor's language, from the browser. */
function describe(codes: string[], locale: string): Option[] {
    let names: Intl.DisplayNames | null = null;
    try {
        names = new Intl.DisplayNames([locale, 'en'], { type: 'currency' });
    } catch {
        names = null;
    }

    return codes.map((code) => {
        let symbol = code;
        try {
            symbol =
                new Intl.NumberFormat(locale, { style: 'currency', currency: code, currencyDisplay: 'narrowSymbol' })
                    .formatToParts(0)
                    .find((p) => p.type === 'currency')?.value ?? code;
        } catch {
            symbol = code;
        }

        return { code, name: names?.of(code) ?? code, symbol };
    });
}

/**
 * Searchable picker over every ISO 4217 currency (codes come from
 * config/currencies.php). Search matches code, name or symbol.
 */
export default function CurrencySelect({
    id,
    value,
    onChange,
    currencies,
}: {
    id?: string;
    value: string;
    onChange: (code: string) => void;
    currencies: string[];
}) {
    const { __, locale } = useTranslate();
    const [query, setQuery] = useState('');

    const options = useMemo(() => describe(currencies, locale), [currencies, locale]);
    const selected = options.find((o) => o.code === value);

    const q = query.trim().toLowerCase();
    const matches = q === '' ? options : options.filter((o) => `${o.code} ${o.name} ${o.symbol}`.toLowerCase().includes(q));
    const popular = q === '' ? POPULAR.map((c) => options.find((o) => o.code === c)).filter((o): o is Option => Boolean(o)) : [];
    const rest = q === '' ? matches.filter((o) => !POPULAR.includes(o.code)) : matches;

    const item = (o: Option) => (
        <DropdownMenuItem key={o.code} onSelect={() => onChange(o.code)} className="gap-2">
            <span className="w-10 font-mono text-xs font-semibold">{o.code}</span>
            <span className="flex-1 truncate">{o.name}</span>
            <span className="text-xs text-muted-foreground">{o.symbol}</span>
            <Check className={cn('size-4', o.code === value ? 'opacity-100' : 'opacity-0')} />
        </DropdownMenuItem>
    );

    return (
        <DropdownMenu onOpenChange={(open) => !open && setQuery('')}>
            <DropdownMenuTrigger
                id={id}
                className="flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
                <span className="flex min-w-0 items-center gap-2">
                    <span className="font-mono font-semibold">{value || '—'}</span>
                    {selected ? (
                        <span className="truncate text-muted-foreground">
                            {selected.name} ({selected.symbol})
                        </span>
                    ) : (
                        // A code saved before this list existed (e.g. "DAM" for MAD).
                        value && <span className="truncate text-xs text-amber-600 dark:text-amber-400">{__('Not a valid currency — choose one')}</span>
                    )}
                </span>
                <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground" />
            </DropdownMenuTrigger>

            <DropdownMenuContent align="start" className="w-[min(22rem,calc(100vw-2rem))] p-0">
                <div className="flex items-center gap-2 border-b px-3">
                    <Search className="size-4 text-muted-foreground" />
                    <input
                        autoFocus
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        // Keep typing in the box instead of the menu's type-to-select.
                        onKeyDown={(e) => e.key !== 'Escape' && e.key !== 'ArrowDown' && e.stopPropagation()}
                        placeholder={__('Search currency…')}
                        className="h-10 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    />
                </div>
                <div className="max-h-72 overflow-y-auto p-1">
                    {popular.length > 0 && (
                        <>
                            <DropdownMenuLabel className="text-xs text-muted-foreground">{__('Popular')}</DropdownMenuLabel>
                            {popular.map(item)}
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel className="text-xs text-muted-foreground">{__('All currencies')}</DropdownMenuLabel>
                        </>
                    )}
                    {rest.map(item)}
                    {matches.length === 0 && <p className="px-3 py-6 text-center text-sm text-muted-foreground">{__('No currency found.')}</p>}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
