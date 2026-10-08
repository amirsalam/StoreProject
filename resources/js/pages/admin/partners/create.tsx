import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import PartnerForm, { type PartnerFormData } from './partner-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Partners', href: '/admin/partners' },
    { title: 'New', href: '/admin/partners/create' },
];

export default function AdminPartnersCreate() {
    const { __ } = useTranslate();
    const { data, setData, post, processing, errors } = useForm<PartnerFormData>({
        name: '',
        website_url: '',
        is_active: true,
        logo: null,
        remove_logo: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.partners.store'), { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('New partner · Admin')} />
            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:py-8">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">{__('New partner')}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">{__('Add a logo to the “Trusted by” strip on the homepage.')}</p>
                </div>
                <PartnerForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={submit}
                    submitLabel={__('Add partner')}
                />
            </div>
        </AppLayout>
    );
}
