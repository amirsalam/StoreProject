import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import ProductForm, { type LicensingRule, type ProductFormValues, type UploadOptions } from '@/pages/admin/products/product-form';
import { type BreadcrumbItem, type Category, type ProductType } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Option {
    value: string;
    label: string;
}

interface AdminProductsCreateProps {
    upload: UploadOptions;
    licensing: LicensingRule;
    currencies: string[];
    categories: Category[];
    statuses: Option[];
    types: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Products', href: '/admin/products' },
    { title: 'New', href: '/admin/products/create' },
];

export default function AdminProductsCreate({ categories, statuses, types, upload, licensing, currencies }: AdminProductsCreateProps) {
    const { __ } = useTranslate();
    const { data, setData, post, processing, errors } = useForm<ProductFormValues>({
        category_id: '',
        title: '',
        slug: '',
        short_description: '',
        description: '',
        type: 'digital_download' as ProductType,
        price: '',
        sale_price: '',
        currency: 'USD',
        thumbnail: '',
        version: '',
        license_type: '',
        default_activation_limit: 1,
        download_limit: '',
        status: 'draft',
        is_featured: false,
        seo_title: '',
        seo_description: '',
        extended_price: '',
        support_months: '6',
        support_extension_price: '',
        live_preview_url: '',
        screenshots: '',
        download_file: null,
        download_file_token: '',
        remove_download_file: false,
        extended_file: null,
        extended_file_token: '',
        remove_extended_file: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.products.store'), { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('New product')} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{__('New product')}</h1>
                    <p className="text-muted-foreground text-sm">{__('Add a new digital product to your store.')}</p>
                </div>

                <ProductForm
                    data={data}
                    setData={setData}
                    errors={errors as Partial<Record<keyof ProductFormValues, string>>}
                    processing={processing}
                    submitLabel={__('Create product')}
                    onSubmit={submit}
                    categories={categories}
                    statuses={statuses}
                    types={types}
                    upload={upload}
                    licensing={licensing}
                    currencies={currencies}
                    cancelHref={route('admin.products.index')}
                />
            </div>
        </AppLayout>
    );
}
