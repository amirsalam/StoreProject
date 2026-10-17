import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import FaqForm, { emptyTexts, type FaqFormData } from './faq-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'FAQ', href: '/admin/faqs' },
    { title: 'New', href: '/admin/faqs/create' },
];

export default function AdminFaqsCreate({ locales }: { locales: string[] }) {
    const { __ } = useTranslate();
    const { data, setData, post, processing, errors } = useForm<FaqFormData>({
        question: emptyTexts(locales),
        answer: emptyTexts(locales),
        is_active: true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.faqs.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('New question · Admin')} />
            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:py-8">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">{__('New question')}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">{__('Add a question to the “Frequently asked” section on the homepage.')}</p>
                </div>
                <FaqForm
                    locales={locales}
                    data={data}
                    setData={setData}
                    errors={errors as Record<string, string | undefined>}
                    processing={processing}
                    onSubmit={submit}
                    submitLabel={__('Add question')}
                />
            </div>
        </AppLayout>
    );
}
