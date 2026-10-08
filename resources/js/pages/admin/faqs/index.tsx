import { useConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Eye, EyeOff, MessageCircleQuestion, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { LANGUAGE_NAMES, type Faq } from './faq-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'FAQ', href: '/admin/faqs' },
];

/** Question in the dashboard's language when it has one, English otherwise. */
function questionIn(faq: Faq, locale: string): string {
    return faq.question[locale]?.trim() || faq.question.en || '';
}

export default function AdminFaqsIndex({ faqs, locales }: { faqs: Faq[]; locales: string[] }) {
    const { __ } = useTranslate();
    const { flash, locale } = usePage<{ flash: { success: string | null }; locale?: string }>().props;
    const [busy, setBusy] = useState(false);
    const { ask, confirmDialog } = useConfirmDialog();

    const post = (url: string, data: Record<string, string> = {}) =>
        router.post(url, data, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });

    const handleDelete = (faq: Faq) =>
        ask({
            title: __('Delete this question?'),
            description: __('It is removed from the homepage in every language. This cannot be undone.'),
            confirmLabel: __('Delete'),
            destructive: true,
            action: (finish) => router.delete(route('admin.faqs.destroy', faq.id), { preserveScroll: true, onFinish: finish }),
        });

    const visibleCount = faqs.filter((f) => f.is_active).length;
    const shownIn = locale ?? 'en';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('FAQ · Admin')} />

            <div className="mx-auto w-full max-w-5xl space-y-6 px-4 py-6 sm:py-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">{__('FAQ')}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {__('Questions in the “Frequently asked” section on the homepage. :count shown.', { count: visibleCount })}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.faqs.create')}>
                            <Plus /> {__('New question')}
                        </Link>
                    </Button>
                </div>

                {faqs.length === 0 ? (
                    <div className="bg-card text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center text-sm">
                        <MessageCircleQuestion className="size-8 opacity-50" />
                        {__('No questions yet. The section is hidden on the homepage until you add one.')}
                    </div>
                ) : (
                    <ul className="bg-card divide-y overflow-hidden rounded-xl border shadow-sm">
                        {faqs.map((faq, i) => {
                            const missing = locales.filter((l) => !faq.question[l]?.trim() || !faq.answer[l]?.trim());
                            return (
                                <li key={faq.id} className="flex flex-wrap items-center gap-4 p-4 sm:flex-nowrap">
                                    <span className="text-muted-foreground w-6 shrink-0 text-center font-mono text-xs">{i + 1}</span>

                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">{questionIn(faq, shownIn)}</span>
                                            {faq.is_active ? (
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
                                        <div className="text-muted-foreground mt-0.5 text-xs">
                                            {missing.length === 0
                                                ? __('Translated in every language')
                                                : __('Shows English in: :languages', {
                                                      languages: missing.map((l) => LANGUAGE_NAMES[l] ?? l).join(', '),
                                                  })}
                                        </div>
                                    </div>

                                    <div className="flex shrink-0 items-center gap-1">
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            title={__('Move up')}
                                            aria-label={__('Move up')}
                                            disabled={busy || i === 0}
                                            onClick={() => post(route('admin.faqs.move', faq.id), { direction: 'up' })}
                                        >
                                            <ArrowUp />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            title={__('Move down')}
                                            aria-label={__('Move down')}
                                            disabled={busy || i === faqs.length - 1}
                                            onClick={() => post(route('admin.faqs.move', faq.id), { direction: 'down' })}
                                        >
                                            <ArrowDown />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            title={faq.is_active ? __('Hide') : __('Show')}
                                            aria-label={faq.is_active ? __('Hide') : __('Show')}
                                            disabled={busy}
                                            onClick={() => post(route('admin.faqs.toggle', faq.id))}
                                        >
                                            {faq.is_active ? <EyeOff /> : <Eye />}
                                        </Button>
                                        <Button asChild size="icon" variant="ghost" title={__('Edit')} aria-label={__('Edit')}>
                                            <Link href={route('admin.faqs.edit', faq.id)}>
                                                <Pencil />
                                            </Link>
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            title={__('Delete')}
                                            aria-label={__('Delete')}
                                            disabled={busy}
                                            onClick={() => handleDelete(faq)}
                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            {confirmDialog}
        </AppLayout>
    );
}
