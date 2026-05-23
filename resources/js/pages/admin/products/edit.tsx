import AppLayout from '@/layouts/app-layout';
import ProductForm, { type ProductFormValues } from '@/pages/admin/products/product-form';
import { type BreadcrumbItem, type Category, type Product, type ProductType } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Option {
    value: string;
    label: string;
}

interface FullProduct extends Product {
    status: string;
    description: string | null;
    download_file_path: string | null;
    default_activation_limit: number;
    download_limit: number | null;
    seo_title: string | null;
    seo_description: string | null;
}

interface AdminProductsEditProps {
    product: FullProduct;
    categories: Category[];
    statuses: Option[];
    types: Option[];
}

export default function AdminProductsEdit({ product, categories, statuses, types }: AdminProductsEditProps) {
    const { data, setData, put, processing, errors } = useForm<ProductFormValues>({
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
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin/products' },
        { title: 'Products', href: '/admin/products' },
        { title: product.title, href: `/admin/products/${product.id}/edit` },
    ];

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.products.update', product.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit · ${product.title}`} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Edit product</h1>
                    <p className="text-sm text-muted-foreground">{product.title}</p>
                </div>

                <ProductForm
                    data={data}
                    setData={setData}
                    errors={errors as Partial<Record<keyof ProductFormValues, string>>}
                    processing={processing}
                    submitLabel="Save changes"
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
