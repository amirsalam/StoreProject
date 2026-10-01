import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type Category, type ProductType } from '@/types';
import { Link } from '@inertiajs/react';
import { DirectUploadError, directUpload } from '@/lib/direct-upload';
import { CheckCircle2, CloudUpload, FileArchive, Upload, X } from 'lucide-react';
import { FormEvent, useRef, useState } from 'react';
import { useTranslate } from '@/hooks/use-translate';

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
    /** Price of the Extended License; blank = Regular License only. */
    extended_price: string;
    /** Months of support included (0 = none). */
    support_months: string;
    /** Price to extend support to 12 months; blank = not offered. */
    support_extension_price: string;
    live_preview_url: string;
    /** Screenshot URLs, one per line. */
    screenshots: string;
    /** The file buyers download (sent as multipart). */
    download_file: File | null;
    /** Set instead of download_file after a direct-to-cloud upload. */
    download_file_token: string;
    remove_download_file: boolean;
    [key: string]: string | number | boolean | File | null;
}

/** How files are uploaded: straight to cloud storage, or to this server in chunks. */
export interface UploadOptions {
    mode: 'direct' | 'chunked';
    max_bytes: number;
}

/** The file already stored for this product (its path never reaches the browser). */
export interface CurrentProductFile {
    name: string | null;
    size: number | null;
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
    currentFile?: CurrentProductFile | null;
    /** Admins can feature products on the storefront; sellers can't. */
    showFeatured?: boolean;
    upload: UploadOptions;
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
    currentFile = null,
    showFeatured = true,
    upload,
}: ProductFormProps) {
    const { __ } = useTranslate();
    // A direct upload is still running: saving now would lose the file.
    const [uploading, setUploading] = useState(false);
    return (
        <form onSubmit={onSubmit} className="space-y-8">
            <section className="space-y-4 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">{__('Basics')}</h2>

                <Field label={__('Title')} htmlFor="title" error={errors.title} required>
                    <Input
                        id="title"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        required
                        autoFocus
                    />
                </Field>

                <Field label={__('Slug')} htmlFor="slug" error={errors.slug} hint={__('URL-safe identifier. Auto-derived from title if left blank.')}>
                    <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                </Field>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field label={__('Type')} htmlFor="type" error={errors.type} required>
                        <Select value={data.type} onValueChange={(v) => setData('type', v as ProductType)}>
                            <SelectTrigger id="type">
                                <SelectValue placeholder={__('Select type')} />
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

                    <Field label={__('Category')} htmlFor="category_id" error={errors.category_id}>
                        <Select
                            value={data.category_id}
                            onValueChange={(v) => setData('category_id', v === '__none__' ? '' : v)}
                        >
                            <SelectTrigger id="category_id">
                                <SelectValue placeholder={__('Uncategorized')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">{__('Uncategorized')}</SelectItem>
                                {categories.map((cat) => (
                                    <SelectItem key={cat.id} value={String(cat.id)}>
                                        {cat.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                </div>

                <Field label={__('Short description')} htmlFor="short_description" error={errors.short_description}>
                    <Input
                        id="short_description"
                        value={data.short_description}
                        onChange={(e) => setData('short_description', e.target.value)}
                        maxLength={255}
                    />
                </Field>

                <Field label={__('Description')} htmlFor="description" error={errors.description}>
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
                <h2 className="text-base font-semibold">{__('Pricing')}</h2>

                <div className="grid gap-4 md:grid-cols-3">
                    <Field label={__('Price')} htmlFor="price" error={errors.price} required>
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
                    <Field label={__('Sale price')} htmlFor="sale_price" error={errors.sale_price} hint={__('Optional. Must be lower than price.')}>
                        <Input
                            id="sale_price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.sale_price}
                            onChange={(e) => setData('sale_price', e.target.value)}
                        />
                    </Field>
                    <Field label={__('Currency')} htmlFor="currency" error={errors.currency} required>
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
                <h2 className="text-base font-semibold">{__('License & support')}</h2>

                <div className="grid gap-4 md:grid-cols-3">
                    <Field
                        label={__('Extended License price')}
                        htmlFor="extended_price"
                        error={errors.extended_price}
                        hint={__('Leave blank to sell the Regular License only.')}
                    >
                        <Input
                            id="extended_price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.extended_price}
                            onChange={(e) => setData('extended_price', e.target.value)}
                        />
                    </Field>
                    <Field label={__('Support included (months)')} htmlFor="support_months" error={errors.support_months} hint={__('0 = no support.')}>
                        <Input
                            id="support_months"
                            type="number"
                            min={0}
                            max={60}
                            value={data.support_months}
                            onChange={(e) => setData('support_months', e.target.value)}
                        />
                    </Field>
                    <Field
                        label={__('Extend support to 12 months — price')}
                        htmlFor="support_extension_price"
                        error={errors.support_extension_price}
                        hint={__('Leave blank to not offer it.')}
                    >
                        <Input
                            id="support_extension_price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.support_extension_price}
                            onChange={(e) => setData('support_extension_price', e.target.value)}
                        />
                    </Field>
                </div>
            </section>

            <section className="space-y-4 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">{__('Preview')}</h2>

                <Field label={__('Live preview URL')} htmlFor="live_preview_url" error={errors.live_preview_url} hint={__('A demo buyers can try before buying.')}>
                    <Input
                        id="live_preview_url"
                        dir="ltr"
                        type="url"
                        placeholder="https://demo.example.com"
                        value={data.live_preview_url}
                        onChange={(e) => setData('live_preview_url', e.target.value)}
                    />
                </Field>
                <Field label={__('Screenshots')} htmlFor="screenshots" error={errors.screenshots ?? errors.gallery} hint={__('One image URL per line (up to 20).')}>
                    <textarea
                        id="screenshots"
                        dir="ltr"
                        value={data.screenshots}
                        onChange={(e) => setData('screenshots', e.target.value)}
                        rows={4}
                        placeholder="https://…/screenshot-1.png"
                        className="flex w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-hidden"
                    />
                </Field>
            </section>

            <section className="space-y-4 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">{__('Delivery')}</h2>

                <DownloadFileField
                    upload={upload}
                    current={currentFile}
                    remove={data.remove_download_file}
                    onUploaded={(token) => {
                        setData('download_file_token', token ?? '');
                        if (token) setData('remove_download_file', false);
                    }}
                    onBusy={setUploading}
                    onRemove={(r) => setData('remove_download_file', r)}
                    error={errors.download_file ?? errors.download_file_token}
                />

                <div className="grid gap-4 md:grid-cols-2">
                    <Field label={__('Version')} htmlFor="version" error={errors.version}>
                        <Input id="version" value={data.version} onChange={(e) => setData('version', e.target.value)} />
                    </Field>
                    <Field label={__('License type')} htmlFor="license_type" error={errors.license_type} hint={__('e.g. single-site, unlimited, developer')}>
                        <Input
                            id="license_type"
                            value={data.license_type}
                            onChange={(e) => setData('license_type', e.target.value)}
                        />
                    </Field>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        label={__('Default activation limit')}
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
                    <Field label={__('Download limit')} htmlFor="download_limit" error={errors.download_limit} hint={__('Leave blank for unlimited.')}>
                        <Input
                            id="download_limit"
                            type="number"
                            min={1}
                            value={data.download_limit}
                            onChange={(e) => setData('download_limit', e.target.value)}
                        />
                    </Field>
                </div>

                <Field label={__('Thumbnail URL')} htmlFor="thumbnail" error={errors.thumbnail}>
                    <Input id="thumbnail" value={data.thumbnail} onChange={(e) => setData('thumbnail', e.target.value)} />
                </Field>
            </section>

            <section className="space-y-4 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">{__('Visibility')}</h2>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field label={__('Status')} htmlFor="status" error={errors.status} required>
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
                    {showFeatured && (
                    <div className="flex items-center gap-2 pt-7">
                        <Checkbox
                            id="is_featured"
                            checked={data.is_featured}
                            onCheckedChange={(v) => setData('is_featured', v === true)}
                        />
                        <Label htmlFor="is_featured" className="cursor-pointer">
                            {__('Featured product')}
                        </Label>
                    </div>
                    )}
                </div>
            </section>

            <section className="space-y-4 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">{__('SEO')}</h2>
                <Field label={__('SEO title')} htmlFor="seo_title" error={errors.seo_title}>
                    <Input id="seo_title" value={data.seo_title} onChange={(e) => setData('seo_title', e.target.value)} />
                </Field>
                <Field label={__('SEO description')} htmlFor="seo_description" error={errors.seo_description}>
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
                    <Link href={cancelHref}>{__('Cancel')}</Link>
                </Button>
                <Button type="submit" disabled={processing || uploading}>
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

export function formatBytes(bytes: number | null | undefined): string {
    if (!bytes) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }
    return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

type UploadState = { status: 'idle' } | { status: 'uploading'; percent: number } | { status: 'done' } | { status: 'error'; message: string };

function DownloadFileField({
    upload,
    current,
    remove,
    onUploaded,
    onBusy,
    onRemove,
    error,
}: {
    upload: UploadOptions;
    current: CurrentProductFile | null;
    remove: boolean;
    onUploaded: (token: string | null) => void;
    onBusy: (busy: boolean) => void;
    onRemove: (remove: boolean) => void;
    error?: string;
}) {
    const { __ } = useTranslate();
    const [chosen, setChosen] = useState<File | null>(null);
    const [state, setState] = useState<UploadState>({ status: 'idle' });
    const abort = useRef<AbortController | null>(null);
    const direct = upload.mode === 'direct';
    const maxLabel = formatBytes(upload.max_bytes);

    const reset = () => {
        abort.current?.abort();
        abort.current = null;
        setChosen(null);
        setState({ status: 'idle' });
        onBusy(false);
        onUploaded(null);
    };

    const choose = async (file: File | null) => {
        reset();
        if (!file) return;

        if (file.size > upload.max_bytes) {
            setState({ status: 'error', message: __('The file is too large. The maximum is :size.', { size: maxLabel }) });
            return;
        }

        setChosen(file);

        // Uploaded now (to the bucket, or to this server in chunks), with
        // progress; the form then submits the token.
        const controller = new AbortController();
        abort.current = controller;
        onBusy(true);
        setState({ status: 'uploading', percent: 0 });
        try {
            const token = await directUpload(file, (percent) => setState({ status: 'uploading', percent }), controller.signal);
            onUploaded(token);
            setState({ status: 'done' });
        } catch (e) {
            if (controller.signal.aborted) return;
            const blocked = e instanceof DirectUploadError && e.blockedByBucket;
            setState({
                status: 'error',
                message: blocked ? __('The storage bucket refused the upload. Check its CORS rules in Admin → File storage.') : (e as Error).message,
            });
            setChosen(null);
        } finally {
            if (abort.current === controller) {
                abort.current = null;
                onBusy(false);
            }
        }
    };

    const hasCurrent = Boolean(current?.name) && !remove;

    return (
        <div className="space-y-2">
            <Label htmlFor="download_file">{__('Product file')}</Label>
            <p className="text-xs text-muted-foreground">
                {__('The file buyers download after paying (zip, pdf…). It is stored privately — only buyers can download it.')}
            </p>

            {chosen ? (
                <div className="space-y-2 rounded-md border border-primary/40 bg-primary/5 px-3 py-2 text-sm">
                    <div className="flex items-center justify-between gap-3">
                        <span className="flex min-w-0 items-center gap-2">
                            {state.status === 'done' ? (
                                <CheckCircle2 className="size-4 shrink-0 text-emerald-600" />
                            ) : direct ? (
                                <CloudUpload className="size-4 shrink-0 text-primary" />
                            ) : (
                                <Upload className="size-4 shrink-0 text-primary" />
                            )}
                            <span className="truncate" dir="ltr">
                                {chosen.name}
                            </span>
                            <span className="shrink-0 text-xs text-muted-foreground">{formatBytes(chosen.size)}</span>
                        </span>
                        <Button type="button" size="sm" variant="ghost" onClick={reset} title={__('Cancel')}>
                            <X />
                        </Button>
                    </div>
                    {state.status === 'uploading' && (
                        <div className="space-y-1">
                            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${state.percent}%` }} />
                            </div>
                            <p className="text-xs text-muted-foreground">{__('Uploading… :percent%', { percent: state.percent })}</p>
                        </div>
                    )}
                    {state.status === 'done' && (
                        <p className="text-xs text-emerald-700 dark:text-emerald-400">{__('Uploaded — save to attach it to the product.')}</p>
                    )}
                </div>
            ) : hasCurrent ? (
                <div className="flex items-center justify-between gap-3 rounded-md border bg-muted/30 px-3 py-2 text-sm">
                    <span className="flex min-w-0 items-center gap-2">
                        <FileArchive className="size-4 shrink-0 text-muted-foreground" />
                        <span className="truncate" dir="ltr">
                            {current?.name}
                        </span>
                        <span className="shrink-0 text-xs text-muted-foreground">{formatBytes(current?.size)}</span>
                    </span>
                    <Button type="button" size="sm" variant="ghost" className="text-destructive" onClick={() => onRemove(true)}>
                        {__('Remove')}
                    </Button>
                </div>
            ) : remove ? (
                <div className="flex items-center justify-between gap-3 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground">
                    <span>{__('The current file will be removed when you save.')}</span>
                    <Button type="button" size="sm" variant="ghost" onClick={() => onRemove(false)}>
                        {__('Undo')}
                    </Button>
                </div>
            ) : null}

            {/* Re-mounted when the choice is cleared, so the native input empties too. */}
            <Input key={chosen ? 'chosen' : 'empty'} id="download_file" type="file" onChange={(e) => choose(e.target.files?.[0] ?? null)} />
            <p className="text-xs text-muted-foreground">
                {direct
                    ? __('Uploaded straight to cloud storage — up to :size.', { size: maxLabel })
                    : __('Maximum file size: :size', { size: maxLabel })}
            </p>
            {hasCurrent && !chosen && <p className="text-xs text-muted-foreground">{__('Choose a new file to replace the current one.')}</p>}
            <InputError message={state.status === 'error' ? state.message : error} />
        </div>
    );
}
