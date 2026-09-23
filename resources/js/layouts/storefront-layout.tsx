import BrandLockup from '@/components/brand-lockup';
import LocaleSwitcher from '@/components/locale-switcher';
import ThemeToggle from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import { Container } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Menu, ShoppingBag, X } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function StorefrontLayout({ children }: { children: React.ReactNode }) {
    const { auth, cart } = usePage<SharedData>().props;
    const { t, direction } = useTranslate();
    const [scrolled, setScrolled] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const cartCount = cart?.count ?? 0;

    const navLinks = [
        { label: t('nav.products'), href: '/products' },
        { label: t('nav.pricing'), href: '/#pricing' },
        { label: t('nav.customers'), href: route('customers') },
        { label: t('nav.docs'), href: '/#faq' },
    ];

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const cartAriaLabel =
        cartCount === 1 ? t('cart.aria_label_one') : t('cart.aria_label', { count: cartCount });

    const directionArrow = direction === 'rtl' ? '←' : '→';

    return (
        <div className="relative flex min-h-screen flex-col bg-background text-foreground">
            <header
                className={cn(
                    'sticky top-0 z-40 transition-[background-color,border-color,backdrop-filter] duration-200',
                    scrolled
                        ? 'border-b border-border/60 bg-background/80 backdrop-blur-xl supports-[backdrop-filter]:bg-background/60'
                        : 'border-b border-transparent bg-transparent',
                )}
            >
                <Container className="flex h-14 items-center justify-between gap-6">
                    <div className="flex items-center gap-8">
                        <Link href="/">
                            <BrandLockup />
                        </Link>
                        <nav className="hidden items-center gap-1 md:flex">
                            {navLinks.map((link) => (
                                <Link
                                    key={link.href}
                                    href={link.href}
                                    className="rounded-md px-3 py-1.5 text-[13px] font-medium text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    {link.label}
                                </Link>
                            ))}
                        </nav>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route('cart.show')}
                            aria-label={cartAriaLabel}
                            className="relative inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            <ShoppingBag className="size-4" />
                            {cartCount > 0 && (
                                <span className="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 font-mono text-[10px] font-semibold text-primary-foreground shadow-md shadow-primary/30">
                                    {cartCount > 99 ? '99+' : cartCount}
                                </span>
                            )}
                        </Link>
                        <LocaleSwitcher />
                        <ThemeToggle />
                        <div className="hidden items-center gap-2 md:flex">
                            {auth?.user ? (
                                <Button asChild size="sm">
                                    <Link href={route('dashboard')}>{t('common.dashboard')}</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={route('login')}>{t('common.log_in')}</Link>
                                    </Button>
                                    <Button asChild size="sm">
                                        <Link href={route('register')}>
                                            {t('common.get_started')}
                                            <span aria-hidden className="ms-0.5">{directionArrow}</span>
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </div>
                        <button
                            type="button"
                            className="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground md:hidden"
                            onClick={() => setMobileOpen((o) => !o)}
                            aria-label={mobileOpen ? t('nav.close_menu') : t('nav.open_menu')}
                            aria-expanded={mobileOpen}
                        >
                            {mobileOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                        </button>
                    </div>
                </Container>

                {/* Mobile menu */}
                <div
                    className={cn(
                        'overflow-hidden border-t border-border/60 bg-background/95 backdrop-blur md:hidden',
                        mobileOpen ? 'max-h-96' : 'max-h-0',
                        'transition-[max-height] duration-300 ease-out',
                    )}
                >
                    <Container className="flex flex-col gap-1 py-3">
                        {navLinks.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                onClick={() => setMobileOpen(false)}
                                className="rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-muted"
                            >
                                {link.label}
                            </Link>
                        ))}
                        <div className="my-2 border-t border-border/60" />
                        {auth?.user ? (
                            <Button asChild size="sm" className="justify-center">
                                <Link href={route('dashboard')}>{t('common.dashboard')}</Link>
                            </Button>
                        ) : (
                            <div className="flex flex-col gap-2">
                                <Button asChild size="sm" variant="outline" className="justify-center">
                                    <Link href={route('login')}>{t('common.log_in')}</Link>
                                </Button>
                                <Button asChild size="sm" className="justify-center">
                                    <Link href={route('register')}>{t('common.get_started')}</Link>
                                </Button>
                            </div>
                        )}
                    </Container>
                </div>
            </header>

            <main className="relative flex-1">{children}</main>

            <SiteFooter />
        </div>
    );
}

function SiteFooter() {
    const { t } = useTranslate();

    const columns: { titleKey: string; links: { labelKey: string; href: string }[] }[] = [
        {
            titleKey: 'footer.columns.product.title',
            links: [
                { labelKey: 'footer.columns.product.browse', href: '/products' },
                { labelKey: 'footer.columns.product.pricing', href: '/#pricing' },
                { labelKey: 'footer.columns.product.changelog', href: '#' },
                { labelKey: 'footer.columns.product.roadmap', href: '#' },
            ],
        },
        {
            titleKey: 'footer.columns.resources.title',
            links: [
                { labelKey: 'footer.columns.resources.docs', href: '#' },
                { labelKey: 'footer.columns.resources.guides', href: '#' },
                { labelKey: 'footer.columns.resources.api', href: '#' },
                { labelKey: 'footer.columns.resources.status', href: '#' },
            ],
        },
        {
            titleKey: 'footer.columns.company.title',
            links: [
                { labelKey: 'footer.columns.company.about', href: route('about') },
                { labelKey: 'footer.columns.company.blog', href: route('blog.index') },
                { labelKey: 'footer.columns.company.customers', href: route('customers') },
                { labelKey: 'footer.columns.company.contact', href: route('contact') },
            ],
        },
        {
            titleKey: 'footer.columns.legal.title',
            links: [
                { labelKey: 'footer.columns.legal.terms', href: route('terms') },
                { labelKey: 'footer.columns.legal.privacy', href: '#' },
                { labelKey: 'footer.columns.legal.license', href: '#' },
                { labelKey: 'footer.columns.legal.refunds', href: '#' },
            ],
        },
    ];

    return (
        <footer className="relative border-t border-border/60 bg-background">
            <Container className="py-16">
                <div className="grid gap-12 lg:grid-cols-[1.5fr_2fr]">
                    <div className="max-w-sm space-y-4">
                        <Link href="/">
                            <BrandLockup />
                        </Link>
                        <p className="text-sm text-muted-foreground">{t('footer.tagline')}</p>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <span className="inline-flex size-2 rounded-full bg-emerald-500 animate-pulse-soft" />
                            {t('footer.status_ok')}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-8 sm:grid-cols-4">
                        {columns.map((col) => (
                            <FooterColumn
                                key={col.titleKey}
                                title={t(col.titleKey)}
                                links={col.links.map((l) => ({ label: t(l.labelKey), href: l.href }))}
                            />
                        ))}
                    </div>
                </div>

                <div className="mt-14 flex flex-col items-start justify-between gap-4 border-t border-border/60 pt-8 text-xs text-muted-foreground sm:flex-row sm:items-center">
                    <p>{t('footer.copy', { year: new Date().getFullYear() })}</p>
                    <p className="font-mono">v1.0.0 · built with Laravel + React</p>
                </div>
            </Container>
        </footer>
    );
}

function FooterColumn({ title, links }: { title: string; links: { label: string; href: string }[] }) {
    return (
        <div className="space-y-3">
            <h4 className="font-mono text-[11px] uppercase tracking-wider text-muted-foreground">{title}</h4>
            <ul className="space-y-2">
                {links.map((link) => (
                    <li key={link.label}>
                        <Link
                            href={link.href}
                            className="text-sm text-foreground/80 transition-colors hover:text-foreground"
                        >
                            {link.label}
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}
