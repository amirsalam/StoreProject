import { type SharedData, type TranslationDict } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect } from 'react';

/**
 * Resolve a translation key path against the shared dictionary.
 *
 *   t('hero.title')                     → "The marketplace built for"
 *   t('cart.aria_label', { count: 3 })  → "Open cart (3 items)"
 *
 * If a key is missing, the key path itself is returned so the gap is loud
 * in development.
 */
function resolve(dict: TranslationDict, key: string): unknown {
    return key.split('.').reduce<unknown>((node, segment) => {
        if (node && typeof node === 'object' && segment in (node as Record<string, unknown>)) {
            return (node as Record<string, unknown>)[segment];
        }
        return undefined;
    }, dict);
}

function interpolate(template: string, replacements: Record<string, string | number>): string {
    return template.replace(/:(\w+)/g, (_, name) =>
        Object.prototype.hasOwnProperty.call(replacements, name) ? String(replacements[name]) : `:${name}`,
    );
}

export function useTranslate() {
    const { translations, locale, direction } = usePage<SharedData>().props;

    // Keep <html lang> + <html dir> in sync with the live locale prop.
    useEffect(() => {
        if (typeof document === 'undefined') return;
        document.documentElement.setAttribute('lang', locale);
        document.documentElement.setAttribute('dir', direction);
    }, [locale, direction]);

    const t = useCallback(
        (key: string, replacements?: Record<string, string | number>): string => {
            const value = resolve(translations, key);
            if (typeof value !== 'string') {
                if (import.meta.env.DEV && value === undefined) {
                    // eslint-disable-next-line no-console
                    console.warn(`[i18n] missing key: ${key}`);
                }
                return typeof value === 'string' ? value : key;
            }
            return replacements ? interpolate(value, replacements) : value;
        },
        [translations],
    );

    /**
     * Read a nested array/object value verbatim — useful for lists like
     * features.items.licenses or testimonials.items[0].
     */
    const tList = useCallback(
        <T = unknown>(key: string): T => {
            return (resolve(translations, key) as T) ?? ([] as unknown as T);
        },
        [translations],
    );

    const switchLocale = useCallback((next: string) => {
        if (next === locale) return;
        router.patch(
            route('locale.update'),
            { locale: next },
            { preserveScroll: true },
        );
    }, [locale]);

    return { t, tList, locale, direction, switchLocale };
}
