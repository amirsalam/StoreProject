import { Breadcrumbs } from '@/components/breadcrumbs';
import LocaleSwitcher from '@/components/locale-switcher';
import { SidebarToggle } from '@/components/sidebar-toggle';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import { useTranslate } from '@/hooks/use-translate';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { __ } = useTranslate();
    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center gap-2 border-b px-6 md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarToggle className="-ms-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="ms-auto flex shrink-0 items-center gap-2">
                {/* Language (and text direction) for the whole app. */}
                <LocaleSwitcher />

                {/* Back to the public storefront, from any dashboard page. */}
                <Button asChild variant="outline" size="sm">
                    <Link href={route('home')} aria-label={__('View website')} title={__('View website')}>
                        <Globe className="size-4" />
                        <span className="hidden sm:inline">{__('View website')}</span>
                    </Link>
                </Button>
            </div>
        </header>
    );
}
