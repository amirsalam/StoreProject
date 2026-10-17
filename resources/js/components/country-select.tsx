import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslate } from '@/hooks/use-translate';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';

/** Shown first: the countries most stores pick. */
const POPULAR = ['MA', 'US', 'FR', 'SA', 'AE', 'GB', 'ES', 'DE', 'CA', 'EG'];

interface Option {
    code: string;
    name: string;
}

function regionNames(locale: string): Intl.DisplayNames | null {
    try {
        return new Intl.DisplayNames([locale, 'en'], { type: 'region' });
    } catch {
        return null;
    }
}

/** A country code's name in the given language ("MA" → "Morocco" / "المغرب"). */
export function countryName(code: string | null | undefined, locale: string): string {
    if (!code) return '';
    return regionNames(locale)?.of(code.toUpperCase()) ?? code;
}

/**
 * Searchable picker over every ISO 3166-1 country (codes come from
 * config/countries.php); names are in the visitor's language. Search
 * matches the name in that language, the English name or the code.
 */
export default function CountrySelect({
    id,
    value,
    onChange,
    countries,
    allowEmpty = true,
    disabled = false,
}: {
    id?: string;
    value: string;
    onChange: (code: string) => void;
    countries: string[];
    /** Offer a "clear" action (the field is optional). */
    allowEmpty?: boolean;
    disabled?: boolean;
}) {
    const canClear = allowEmpty && !disabled && Boolean(value);
    const { __, locale } = useTranslate();
    const [query, setQuery] = useState('');

    const options = useMemo(() => {
        const local = regionNames(locale);
        const english = regionNames('en');
        return countries
            .map((code) => ({
                code,
                name: local?.of(code) ?? code,
                search: `${code} ${local?.of(code) ?? ''} ${english?.of(code) ?? ''}`.toLowerCase(),
            }))
            .sort((a, b) => a.name.localeCompare(b.name, locale));
    }, [countries, locale]);
    const selected = options.find((o) => o.code === value);

    const q = query.trim().toLowerCase();
    const matches = q === '' ? options : options.filter((o) => o.search.includes(q));
    const popular = q === '' ? POPULAR.map((c) => options.find((o) => o.code === c)).filter((o): o is (typeof options)[number] => Boolean(o)) : [];
    const rest = q === '' ? matches.filter((o) => !POPULAR.includes(o.code)) : matches;

    const item = (o: Option) => (
        <DropdownMenuItem key={o.code} onSelect={() => onChange(o.code)} className="gap-2">
            <span className="text-muted-foreground w-7 font-mono text-xs font-semibold">{o.code}</span>
            <span className="flex-1 truncate">{o.name}</span>
            <Check className={cn('size-4', o.code === value ? 'opacity-100' : 'opacity-0')} />
        </DropdownMenuItem>
    );

    return (
        <DropdownMenu onOpenChange={(open) => !open && setQuery('')}>
            <div className="relative">
                <DropdownMenuTrigger
                    id={id}
                    disabled={disabled}
                    className="border-input bg-background focus-visible:ring-ring flex h-9 w-full items-center justify-between gap-2 rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-1 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span className="flex min-w-0 items-center gap-2">
                        {selected ? (
                            <>
                                <span className="truncate">{selected.name}</span>
                                <span className="text-muted-foreground font-mono text-xs">{selected.code}</span>
                            </>
                        ) : value ? (
                            // A code saved before this list existed.
                            <span className="truncate text-xs text-amber-600 dark:text-amber-400">
                                {value} — {__('Not a valid country — choose one')}
                            </span>
                        ) : (
                            <span className="text-muted-foreground">{__('Choose a country')}</span>
                        )}
                    </span>
                    <ChevronsUpDown className={cn('text-muted-foreground size-4 shrink-0', canClear && 'invisible')} />
                </DropdownMenuTrigger>
                {canClear && (
                    <button
                        type="button"
                        onClick={() => onChange('')}
                        aria-label={__('Clear')}
                        title={__('Clear')}
                        className="text-muted-foreground hover:text-foreground absolute end-2 top-1/2 -translate-y-1/2 rounded p-0.5"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>

            <DropdownMenuContent align="start" className="w-[min(22rem,calc(100vw-2rem))] p-0">
                <div className="flex items-center gap-2 border-b px-3">
                    <Search className="text-muted-foreground size-4" />
                    <input
                        autoFocus
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        // Keep typing in the box instead of the menu's type-to-select.
                        onKeyDown={(e) => e.key !== 'Escape' && e.key !== 'ArrowDown' && e.stopPropagation()}
                        placeholder={__('Search country…')}
                        className="placeholder:text-muted-foreground h-10 flex-1 bg-transparent text-sm outline-none"
                    />
                </div>
                <div className="max-h-72 overflow-y-auto p-1">
                    {popular.length > 0 && (
                        <>
                            <DropdownMenuLabel className="text-muted-foreground text-xs">{__('Popular')}</DropdownMenuLabel>
                            {popular.map(item)}
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel className="text-muted-foreground text-xs">{__('All countries')}</DropdownMenuLabel>
                        </>
                    )}
                    {rest.map(item)}
                    {matches.length === 0 && <p className="text-muted-foreground px-3 py-6 text-center text-sm">{__('No country found.')}</p>}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
