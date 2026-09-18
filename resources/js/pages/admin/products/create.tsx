import AppLayout from '@/layouts/app-layout';
import ProductForm, { type ProductFormValues } from '@/pages/admin/products/product-form';
import { type BreadcrumbItem, type Category, type ProductType } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Option {
    value: string;
    label: string;
}

interface AdminProductsCreateProps {
    categories: Category[];
    statuses: Option[];
    types: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Products', href: '/admin/products' },
    { title: 'New', href: '/admin/products/create' },
];

export default function AdminProductsCreate({ categories, statuses, types }: AdminProductsCreateProps) {
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
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.products.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New product" />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">New product</h1>
                    <p className="text-sm text-muted-foreground">Add a new digital product to your store.</p>
                </div>

                <ProductForm
                    data={data}
                    setData={setData}
                    errors={errors as Partial<Record<keyof ProductFormValues, string>>}
                    processing={processing}
                    submitLabel="Create product"
                    onSubmit={submit}
                    categories={categories}
                    statuses={statuses}
                    types={types}
                    cancelHref={route('admin.products.index')}
                />
            </div>
        </AppLayout>
    );
}
