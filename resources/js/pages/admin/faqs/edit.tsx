import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import FaqForm, { emptyTexts, type Faq, type FaqFormData } from './faq-form';

export default function AdminFaqsEdit({ faq, locales }: { faq: Faq; locales: string[] }) {
    const { __ } = useTranslate();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin/products' },
        { title: 'FAQ', href: '/admin/faqs' },
        { title: 'Edit', href: `/admin/faqs/${faq.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm<FaqFormData>({
        question: emptyTexts(locales, faq.question),
        answer: emptyTexts(locales, faq.answer),
        is_active: faq.is_active,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.faqs.update', faq.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Edit question · Admin')} />
            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:py-8">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">{__('Edit question')}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">{faq.question.en}</p>
                </div>
                <FaqForm
                    locales={locales}
                    data={data}
                    setData={setData}
                    errors={errors as Record<string, string | undefined>}
                    processing={processing}
                    onSubmit={submit}
                    submitLabel={__('Save changes')}
                />
            </div>
        </AppLayout>
    );
}
