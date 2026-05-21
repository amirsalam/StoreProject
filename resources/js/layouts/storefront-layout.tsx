import AppLogoIcon from '@/components/app-logo-icon';
import ThemeToggle from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import { Container } from '@/components/ui/container';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Menu, ShoppingBag, X } from 'lucide-react';
import { useEffect, useState } from 'react';

const NAV_LINKS = [
    { label: 'Products', href: '/products' },
    { label: 'Pricing', href: '/#pricing' },
    { label: 'Customers', href: '/#testimonials' },
    { label: 'Docs', href: '/#faq' },
];

export default function StorefrontLayout({ children }: { children: React.ReactNode }) {
    const { auth, cart } = usePage<SharedData>().props;
    const [scrolled, setScrolled] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const cartCount = cart?.count ?? 0;

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

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
                        <Link href="/" className="flex items-center gap-2 font-display text-sm font-semibold tracking-tight">
                            <span className="flex size-7 items-center justify-center rounded-md bg-foreground text-background">
                                <AppLogoIcon className="size-4" />
                            </span>
                            <span>StoreProject</span>
                        </Link>
                        <nav className="hidden items-center gap-1 md:flex">
                            {NAV_LINKS.map((link) => (
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
                            aria-label={`Open cart (${cartCount} item${cartCount === 1 ? '' : 's'})`}
                            className="relative inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            <ShoppingBag className="size-4" />
                            {cartCount > 0 && (
                                <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 font-mono text-[10px] font-semibold text-primary-foreground shadow-md shadow-primary/30">
                                    {cartCount > 99 ? '99+' : cartCount}
                                </span>
                            )}
                        </Link>
                        <ThemeToggle />
                        <div className="hidden items-center gap-2 md:flex">
                            {auth?.user ? (
                                <Button asChild size="sm">
                                    <Link href={route('dashboard')}>Dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={route('login')}>Log in</Link>
                                    </Button>
                                    <Button asChild size="sm">
                                        <Link href={route('register')}>
                                            Get started
                                            <span aria-hidden className="ml-0.5">→</span>
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </div>
                        <button
                            type="button"
                            className="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground md:hidden"
                            onClick={() => setMobileOpen((o) => !o)}
                            aria-label={mobileOpen ? 'Close menu' : 'Open menu'}
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
                        {NAV_LINKS.map((link) => (
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
                                <Link href={route('dashboard')}>Dashboard</Link>
                            </Button>
                        ) : (
                            <div className="flex flex-col gap-2">
                                <Button asChild size="sm" variant="outline" className="justify-center">
                                    <Link href={route('login')}>Log in</Link>
                                </Button>
                                <Button asChild size="sm" className="justify-center">
                                    <Link href={route('register')}>Get started</Link>
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
    return (
        <footer className="relative border-t border-border/60 bg-background">
            <Container className="py-16">
                <div className="grid gap-12 lg:grid-cols-[1.5fr_2fr]">
                    <div className="max-w-sm space-y-4">
                        <Link href="/" className="inline-flex items-center gap-2 font-display text-sm font-semibold tracking-tight">
                            <span className="flex size-7 items-center justify-center rounded-md bg-foreground text-background">
                                <AppLogoIcon className="size-4" />
                            </span>
                            StoreProject
                        </Link>
                        <p className="text-sm text-muted-foreground">
                            The single-vendor marketplace for Laravel scripts, APIs, templates, and SaaS — built for makers
                            who ship.
                        </p>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <span className="inline-flex size-2 rounded-full bg-emerald-500 animate-pulse-soft" />
                            All systems operational
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-8 sm:grid-cols-4">
                        <FooterColumn
                            title="Product"
                            links={[
                                { label: 'Browse', href: '/products' },
                                { label: 'Pricing', href: '/#pricing' },
                                { label: 'Changelog', href: '#' },
                                { label: 'Roadmap', href: '#' },
                            ]}
                        />
                        <FooterColumn
                            title="Resources"
                            links={[
                                { label: 'Docs', href: '#' },
                                { label: 'Guides', href: '#' },
                                { label: 'API', href: '#' },
                                { label: 'Status', href: '#' },
                            ]}
                        />
                        <FooterColumn
                            title="Company"
                            links={[
                                { label: 'About', href: '#' },
                                { label: 'Blog', href: '#' },
                                { label: 'Customers', href: '/#testimonials' },
                                { label: 'Contact', href: '#' },
                            ]}
                        />
                        <FooterColumn
                            title="Legal"
                            links={[
                                { label: 'Terms', href: '#' },
                                { label: 'Privacy', href: '#' },
                                { label: 'License', href: '#' },
                                { label: 'Refunds', href: '#' },
                            ]}
                        />
                    </div>
                </div>

                <div className="mt-14 flex flex-col items-start justify-between gap-4 border-t border-border/60 pt-8 text-xs text-muted-foreground sm:flex-row sm:items-center">
                    <p>© {new Date().getFullYear()} StoreProject. All rights reserved.</p>
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
