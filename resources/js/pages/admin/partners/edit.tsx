import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import PartnerForm, { type Partner, type PartnerFormData } from './partner-form';

export default function AdminPartnersEdit({ partner }: { partner: Partner }) {
    const { __ } = useTranslate();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin/products' },
        { title: 'Partners', href: '/admin/partners' },
        { title: partner.name, href: `/admin/partners/${partner.id}/edit` },
    ];

    const { data, setData, post, processing, errors, transform } = useForm<PartnerFormData>({
        name: partner.name,
        website_url: partner.website_url ?? '',
        is_active: partner.is_active,
        logo: null,
        remove_logo: false,
    });

    // Files can't travel in a real PUT; spoof it over a multipart POST.
    transform((values) => ({ ...values, _method: 'put' }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.partners.update', partner.id), { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Edit partner · Admin')} />
            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:py-8">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">{__('Edit partner')}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">{partner.name}</p>
                </div>
                <PartnerForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={submit}
                    submitLabel={__('Save changes')}
                    currentLogoUrl={partner.logo_url}
                />
            </div>
        </AppLayout>
    );
}
