import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslate } from '@/hooks/use-translate';
import { type Locale } from '@/types';
import { Check, Globe } from 'lucide-react';

const LOCALE_META: Record<Locale, { label: string; flag: string }> = {
    en: { label: 'English', flag: '🇬🇧' },
    ar: { label: 'العربية', flag: '🇲🇦' },
    fr: { label: 'Français', flag: '🇫🇷' },
    es: { label: 'Español', flag: '🇪🇸' },
};

export default function LocaleSwitcher() {
    const { t, locale, switchLocale } = useTranslate();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    size="sm"
                    variant="ghost"
                    aria-label={t('common.language.select')}
                    className="h-9 gap-1.5 px-2.5 text-muted-foreground hover:text-foreground"
                >
                    <Globe className="size-4" />
                    <span className="hidden font-mono text-[11px] uppercase tracking-wider sm:inline">
                        {locale}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                {(Object.keys(LOCALE_META) as Locale[]).map((code) => {
                    const meta = LOCALE_META[code];
                    return (
                        <DropdownMenuItem
                            key={code}
                            onClick={() => switchLocale(code)}
                            className="flex items-center gap-2 text-sm"
                        >
                            <span aria-hidden className="text-base leading-none">{meta.flag}</span>
                            <span className="flex-1">{meta.label}</span>
                            <span className="font-mono text-[10px] uppercase tracking-wider text-muted-foreground">
                                {code}
                            </span>
                            {locale === code && <Check className="size-3.5 text-primary" />}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
