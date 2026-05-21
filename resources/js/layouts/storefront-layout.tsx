import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export default function StorefrontLayout({ children }: { children: React.ReactNode }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="border-b border-border/60 backdrop-blur sticky top-0 z-30 bg-background/80">
                <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-6 px-4">
                    <Link href="/" className="flex items-center gap-2 font-semibold">
                        <AppLogoIcon className="size-6" />
                        <span>StoreProject</span>
                    </Link>
                    <nav className="flex items-center gap-1 text-sm">
                        <Link
                            href={route('products.index')}
                            className="rounded-md px-3 py-2 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                        >
                            Products
                        </Link>
                    </nav>
                    <div className="flex items-center gap-2">
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
                                    <Link href={route('register')}>Sign up</Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main className="flex-1">
                <div className="mx-auto w-full max-w-6xl px-4 py-8">{children}</div>
            </main>

            <footer className="border-t border-border/60 py-6 text-center text-xs text-muted-foreground">
                StoreProject — single vendor digital marketplace
            </footer>
        </div>
    );
}
