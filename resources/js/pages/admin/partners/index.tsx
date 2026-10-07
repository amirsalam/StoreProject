import { useConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ExternalLink, Eye, EyeOff, Handshake, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { type Partner } from './partner-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Partners', href: '/admin/partners' },
];

export default function AdminPartnersIndex({ partners }: { partners: Partner[] }) {
    const { __ } = useTranslate();
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const [busy, setBusy] = useState(false);
    const { ask, confirmDialog } = useConfirmDialog();

    const post = (url: string, data: Record<string, string> = {}) =>
        router.post(url, data, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });

    const handleDelete = (p: Partner) =>
        ask({
            title: __('Delete this partner?'),
            description: __('":name" and its logo are removed from the homepage. This cannot be undone.', { name: p.name }),
            confirmLabel: __('Delete'),
            destructive: true,
            action: (finish) => router.delete(route('admin.partners.destroy', p.id), { preserveScroll: true, onFinish: finish }),
        });

    const visibleCount = partners.filter((p) => p.is_active).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Partners · Admin')} />

            <div className="mx-auto w-full max-w-5xl space-y-6 px-4 py-6 sm:py-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">{__('Partners')}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {__('Logos in the “Trusted by” strip on the homepage. :count shown.', { count: visibleCount })}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.partners.create')}>
                            <Plus /> {__('New partner')}
                        </Link>
                    </Button>
                </div>

                {partners.length === 0 ? (
                    <div className="bg-card text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center text-sm">
                        <Handshake className="size-8 opacity-50" />
                        {__('No partners yet. The strip is hidden on the homepage until you add one.')}
                    </div>
                ) : (
                    <ul className="bg-card divide-y overflow-hidden rounded-xl border shadow-sm">
                        {partners.map((p, i) => (
                            <li key={p.id} className="flex flex-wrap items-center gap-4 p-4 sm:flex-nowrap">
                                <div className="bg-muted/30 flex h-12 w-36 shrink-0 items-center justify-center rounded-md border p-2">
                                    {p.logo_url ? (
                                        <img src={p.logo_url} alt={p.name} className="max-h-full max-w-full object-contain" />
                                    ) : (
                                        <span className="font-display text-muted-foreground truncate text-[11px] font-semibold tracking-[0.15em] uppercase">
                                            {p.name}
                                        </span>
                                    )}
                                </div>

                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2 font-medium">
                                        <span className="truncate">{p.name}</span>
                                        {p.is_active ? (
                                            <Badge
                                                variant="secondary"
                                                className="bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                                            >
                                                {__('Visible')}
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">{__('Hidden')}</Badge>
                                        )}
                                    </div>
                                    {p.website_url ? (
                                        <a
                                            href={p.website_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-muted-foreground hover:text-foreground inline-flex max-w-full items-center gap-1 truncate text-xs"
                                        >
                                            <span className="truncate">{p.website_url}</span>
                                            <ExternalLink className="size-3 shrink-0" />
                                        </a>
                                    ) : (
                                        <div className="text-muted-foreground text-xs">
                                            {p.logo_url ? __('Logo, no link') : __('Text only, no link')}
                                        </div>
                                    )}
                                </div>

                                <div className="flex shrink-0 items-center gap-1">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        title={__('Move up')}
                                        aria-label={__('Move up')}
                                        disabled={busy || i === 0}
                                        onClick={() => post(route('admin.partners.move', p.id), { direction: 'up' })}
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        title={__('Move down')}
                                        aria-label={__('Move down')}
                                        disabled={busy || i === partners.length - 1}
                                        onClick={() => post(route('admin.partners.move', p.id), { direction: 'down' })}
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        title={p.is_active ? __('Hide') : __('Show')}
                                        aria-label={p.is_active ? __('Hide') : __('Show')}
                                        disabled={busy}
                                        onClick={() => post(route('admin.partners.toggle', p.id))}
                                    >
                                        {p.is_active ? <EyeOff /> : <Eye />}
                                    </Button>
                                    <Button asChild size="icon" variant="ghost" title={__('Edit')} aria-label={__('Edit')}>
                                        <Link href={route('admin.partners.edit', p.id)}>
                                            <Pencil />
                                        </Link>
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        title={__('Delete')}
                                        aria-label={__('Delete')}
                                        disabled={busy}
                                        onClick={() => handleDelete(p)}
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {confirmDialog}
        </AppLayout>
    );
}
