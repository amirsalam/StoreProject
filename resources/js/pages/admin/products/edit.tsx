import ConfirmDialog from '@/components/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import ProductForm, { type LicensingRule, type ProductFormValues, type UploadOptions } from '@/pages/admin/products/product-form';
import { type BreadcrumbItem, type Category, type Product, type ProductType } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { useTranslate } from '@/hooks/use-translate';

interface Option {
    value: string;
    label: string;
}

interface FullProduct extends Product {
    status: string;
    description: string | null;
    download_file_name: string | null;
    download_file_size: number | null;
    default_activation_limit: number;
    download_limit: number | null;
    seo_title: string | null;
    seo_description: string | null;
    extended_price: string | null;
    support_months: number;
    support_extension_price: string | null;
    live_preview_url: string | null;
    gallery: string[] | null;
}

interface AdminProductsEditProps {
    upload: UploadOptions;
    licensing: LicensingRule;
    currencies: string[];
    product: FullProduct;
    categories: Category[];
    statuses: Option[];
    types: Option[];
}

export default function AdminProductsEdit({ product, categories, statuses, types, upload, licensing, currencies }: AdminProductsEditProps) {
    const { __, __el } = useTranslate();
    const { data, setData, post, transform, processing, errors } = useForm<ProductFormValues>({
        category_id: product.category_id ? String(product.category_id) : '',
        title: product.title,
        slug: product.slug,
        short_description: product.short_description ?? '',
        description: product.description ?? '',
        type: product.type as ProductType,
        price: String(product.price),
        sale_price: product.sale_price ? String(product.sale_price) : '',
        currency: product.currency,
        thumbnail: product.thumbnail ?? '',
        version: product.version ?? '',
        license_type: product.license_type ?? '',
        default_activation_limit: product.default_activation_limit ?? 1,
        download_limit: product.download_limit ? String(product.download_limit) : '',
        status: product.status,
        is_featured: product.is_featured,
        seo_title: product.seo_title ?? '',
        seo_description: product.seo_description ?? '',
        extended_price: product.extended_price ?? '',
        support_months: String(product.support_months ?? 6),
        support_extension_price: product.support_extension_price ?? '',
        live_preview_url: product.live_preview_url ?? '',
        screenshots: (product.gallery ?? []).join('\n'),
        download_file: null,
        download_file_token: '',
        remove_download_file: false,
    });

    // Files need multipart, which only POST supports: spoof the PUT.
    transform((values) => ({ ...values, _method: 'put' }));

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin/products' },
        { title: 'Products', href: '/admin/products' },
        { title: product.title, href: `/admin/products/${product.id}/edit` },
    ];

    // Saving asks for confirmation in a popup first.
    const [confirming, setConfirming] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setConfirming(true);
    };

    const confirmUpdate = () => {
        post(route('admin.products.update', product.id), {
            forceFormData: true,
            onFinish: () => setConfirming(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Edit :name', { name: product.title })} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{__('Edit product')}</h1>
                    <p className="text-muted-foreground text-sm">{product.title}</p>
                </div>

                <ProductForm
                    data={data}
                    setData={setData}
                    errors={errors as Partial<Record<keyof ProductFormValues, string>>}
                    processing={processing}
                    submitLabel={__('Save changes')}
                    onSubmit={submit}
                    categories={categories}
                    statuses={statuses}
                    types={types}
                    upload={upload}
                    licensing={licensing}
                    currencies={currencies}
                    cancelHref={route('admin.products.index')}
                    currentFile={{ name: product.download_file_name, size: product.download_file_size }}
                />
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={__('Save changes to this product?')}
                description={
                    <>
                        {__el('The changes to :title go live immediately — price, status and details included.', {
                            title: <strong className="text-foreground">{product.title}</strong>,
                        })}
                    </>
                }
                confirmLabel={__('Save changes')}
                processing={processing}
                onConfirm={confirmUpdate}
            />
        </AppLayout>
    );
}
