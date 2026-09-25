import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type Category, type ProductType } from '@/types';
import { Link } from '@inertiajs/react';
import { FormEvent } from 'react';

export interface ProductFormValues {
    category_id: string;
    title: string;
    slug: string;
    short_description: string;
    description: string;
    type: ProductType;
    price: string;
    sale_price: string;
    currency: string;
    thumbnail: string;
    version: string;
    license_type: string;
    default_activation_limit: number;
    download_limit: string;
    status: string;
    is_featured: boolean;
    seo_title: string;
    seo_description: string;
    [key: string]: string | number | boolean;
}

interface Option {
    value: string;
    label: string;
}

interface ProductFormProps {
    data: ProductFormValues;
    setData: <K extends keyof ProductFormValues>(key: K, value: ProductFormValues[K]) => void;
    errors: Partial<Record<keyof ProductFormValues, string>>;
    processing: boolean;
    submitLabel: string;
    onSubmit: (e: FormEvent) => void;
    categories: Category[];
    statuses: Option[];
    types: Option[];
    cancelHref: string;
}

export default function ProductForm({
    data,
    setData,
    errors,
    processing,
    submitLabel,
    onSubmit,
    categories,
    statuses,
    types,
    cancelHref,
}: ProductFormProps) {
    return (
        <form onSubmit={onSubmit} className="space-y-8">
            <section className="space-y-4 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">Basics</h2>

                <Field label="Title" htmlFor="title" error={errors.title} required>
                    <Input
                        id="title"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        required
                        autoFocus
                    />
                </Field>

                <Field label="Slug" htmlFor="slug" error={errors.slug} hint="URL-safe identifier. Auto-derived from title if left blank.">
                    <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                </Field>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Type" htmlFor="type" error={errors.type} required>
                        <Select value={data.type} onValueChange={(v) => setData('type', v as ProductType)}>
                            <SelectTrigger id="type">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field label="Category" htmlFor="category_id" error={errors.category_id}>
                        <Select
                            value={data.category_id}
                            onValueChange={(v) => setData('category_id', v === '__none__' ? '' : v)}
                        >
                            <SelectTrigger id="category_id">
                                <SelectValue placeholder="Uncategorized" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">Uncategorized</SelectItem>
                                {categories.map((cat) => (
                                    <SelectItem key={cat.id} value={String(cat.id)}>
                                        {cat.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                </div>

                <Field label="Short description" htmlFor="short_description" error={errors.short_description}>
                    <Input
                        id="short_description"
                        value={data.short_description}
                        onChange={(e) => setData('short_description', e.target.value)}
                        maxLength={255}
                    />
                </Field>

                <Field label="Description" htmlFor="description" error={errors.description}>
                    <textarea
                        id="description"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        rows={6}
                        className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                    />
                </Field>
            </section>

            <section className="space-y-4 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">Pricing</h2>

                <div className="grid gap-4 md:grid-cols-3">
                    <Field label="Price" htmlFor="price" error={errors.price} required>
                        <Input
                            id="price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.price}
                            onChange={(e) => setData('price', e.target.value)}
                            required
                        />
                    </Field>
                    <Field label="Sale price" htmlFor="sale_price" error={errors.sale_price} hint="Optional. Must be lower than price.">
                        <Input
                            id="sale_price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.sale_price}
                            onChange={(e) => setData('sale_price', e.target.value)}
                        />
                    </Field>
                    <Field label="Currency" htmlFor="currency" error={errors.currency} required>
                        <Input
                            id="currency"
                            value={data.currency}
                            onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                            maxLength={3}
                            required
                        />
                    </Field>
                </div>
            </section>

            <section className="space-y-4 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">Delivery</h2>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Version" htmlFor="version" error={errors.version}>
                        <Input id="version" value={data.version} onChange={(e) => setData('version', e.target.value)} />
                    </Field>
                    <Field label="License type" htmlFor="license_type" error={errors.license_type} hint="e.g. single-site, unlimited, developer">
                        <Input
                            id="license_type"
                            value={data.license_type}
                            onChange={(e) => setData('license_type', e.target.value)}
                        />
                    </Field>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        label="Default activation limit"
                        htmlFor="default_activation_limit"
                        error={errors.default_activation_limit}
                        required
                    >
                        <Input
                            id="default_activation_limit"
                            type="number"
                            min={1}
                            value={data.default_activation_limit}
                            onChange={(e) => setData('default_activation_limit', Number(e.target.value))}
                            required
                        />
                    </Field>
                    <Field label="Download limit" htmlFor="download_limit" error={errors.download_limit} hint="Leave blank for unlimited.">
                        <Input
                            id="download_limit"
                            type="number"
                            min={1}
                            value={data.download_limit}
                            onChange={(e) => setData('download_limit', e.target.value)}
                        />
                    </Field>
                </div>

                <Field label="Thumbnail URL" htmlFor="thumbnail" error={errors.thumbnail}>
                    <Input id="thumbnail" value={data.thumbnail} onChange={(e) => setData('thumbnail', e.target.value)} />
                </Field>
            </section>

            <section className="space-y-4 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">Visibility</h2>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Status" htmlFor="status" error={errors.status} required>
                        <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                            <SelectTrigger id="status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statuses.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <div className="flex items-center gap-2 pt-7">
                        <Checkbox
                            id="is_featured"
                            checked={data.is_featured}
                            onCheckedChange={(v) => setData('is_featured', v === true)}
                        />
                        <Label htmlFor="is_featured" className="cursor-pointer">
                            Featured product
                        </Label>
                    </div>
                </div>
            </section>

            <section className="space-y-4 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">SEO</h2>
                <Field label="SEO title" htmlFor="seo_title" error={errors.seo_title}>
                    <Input id="seo_title" value={data.seo_title} onChange={(e) => setData('seo_title', e.target.value)} />
                </Field>
                <Field label="SEO description" htmlFor="seo_description" error={errors.seo_description}>
                    <textarea
                        id="seo_description"
                        value={data.seo_description}
                        onChange={(e) => setData('seo_description', e.target.value)}
                        rows={3}
                        maxLength={500}
                        className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                    />
                </Field>
            </section>

            <div className="flex items-center justify-end gap-2">
                <Button asChild variant="ghost">
                    <Link href={cancelHref}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

function Field({
    label,
    htmlFor,
    children,
    error,
    hint,
    required,
}: {
    label: string;
    htmlFor: string;
    children: React.ReactNode;
    error?: string;
    hint?: string;
    required?: boolean;
}) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={htmlFor}>
                {label}
                {required && <span className="ml-0.5 text-destructive">*</span>}
            </Label>
            {children}
            {hint && !error && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}
