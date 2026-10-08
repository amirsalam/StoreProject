import CurrencySelect from '@/components/currency-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslate } from '@/hooks/use-translate';
import { DirectUploadError, directUpload } from '@/lib/direct-upload';
import { type Category, type ProductType } from '@/types';
import { Link } from '@inertiajs/react';
import { CheckCircle2, CloudUpload, FileArchive, Upload, X } from 'lucide-react';
import { FormEvent, useRef, useState } from 'react';

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
    /** The Extended License's own file (optional) — same fields. */
    extended_file: File | null;
    extended_file_token: string;
    remove_extended_file: boolean;
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
    /** The Extended License's file already stored, if any. */
    currentExtendedFile?: CurrentProductFile | null;
    /** Admins can feature products on the storefront; sellers can't. */
    showFeatured?: boolean;
    upload: UploadOptions;
    /** Store-wide Extended License rule (Admin → Licensing). */
    licensing?: LicensingRule;
    /** ISO 4217 codes products can be priced in (config/currencies.php). */
    currencies?: string[];
}

export interface LicensingRule {
    extended_enabled: boolean;
    extended_multiplier: number;
}

/** "$316.15", "316,15 €" — falls back to "CODE 316.15" for an unknown code. */
function formatMoney(amount: number, currency: string): string {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(amount);
    } catch {
        return `${currency} ${amount.toFixed(2)}`;
    }
}

/** The Extended price buyers see when the field is left blank. */
function defaultExtended(price: string, licensing?: LicensingRule): number | null {
    const regular = parseFloat(price);
    if (!licensing?.extended_enabled || !Number.isFinite(regular)) return null;
    return Math.round(regular * licensing.extended_multiplier * 100) / 100;
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
    currentExtendedFile = null,
    showFeatured = true,
    upload,
    licensing,
    currencies = ['USD'],
}: ProductFormProps) {
    const { __ } = useTranslate();
    // A direct upload is still running: saving now would lose the file.
    const [uploadingRegular, setUploadingRegular] = useState(false);
    const [uploadingExtended, setUploadingExtended] = useState(false);
    const uploading = uploadingRegular || uploadingExtended;
    return (
        <form onSubmit={onSubmit} className="space-y-8">
            <section className="bg-card space-y-4 rounded-lg border p-6">
                <h2 className="text-base font-semibold">{__('Basics')}</h2>

                <Field label={__('Title')} htmlFor="title" error={errors.title} required>
                    <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required autoFocus />
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
                        <Select value={data.category_id} onValueChange={(v) => setData('category_id', v === '__none__' ? '' : v)}>
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
                        className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:outline-hidden"
                    />
                </Field>
            </section>

            <section className="bg-card space-y-4 rounded-lg border p-6">
                <h2 className="text-base font-semibold">{__('Pricing')}</h2>

                <div className="grid gap-4 md:grid-cols-3">
                    <Field
                        label={__('Price')}
                        htmlFor="price"
                        error={errors.price}
                        hint={__('The regular price. Shown crossed out while a sale price is set.')}
                        required
                    >
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
                    <Field
                        label={__('Sale price')}
                        htmlFor="sale_price"
                        error={errors.sale_price}
                        hint={__('Optional — what buyers pay during a sale. Must be lower than the price; clear it to end the sale.')}
                    >
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
                        <CurrencySelect id="currency" value={data.currency} onChange={(code) => setData('currency', code)} currencies={currencies} />
                    </Field>
                </div>

                <PricePreview data={data} licensing={licensing} />
            </section>

            <section className="bg-card space-y-4 rounded-lg border p-6">
                <h2 className="text-base font-semibold">{__('License & support')}</h2>

                <div className="grid gap-4 md:grid-cols-3">
                    <Field
                        label={__('Extended License price')}
                        htmlFor="extended_price"
                        error={errors.extended_price}
                        hint={
                            defaultExtended(data.price, licensing) !== null
                                ? __('Leave blank to use the store default: :price (Regular × :n).', {
                                      price: formatMoney(defaultExtended(data.price, licensing)!, data.currency),
                                      n: licensing!.extended_multiplier,
                                  })
                                : __('Leave blank to sell the Regular License only.')
                        }
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
                    <Field
                        label={__('Support included (months)')}
                        htmlFor="support_months"
                        error={errors.support_months}
                        hint={__('0 = no support.')}
                    >
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

            <section className="bg-card space-y-4 rounded-lg border p-6">
                <h2 className="text-base font-semibold">{__('Preview')}</h2>

                <Field
                    label={__('Live preview URL')}
                    htmlFor="live_preview_url"
                    error={errors.live_preview_url}
                    hint={__('A demo buyers can try before buying.')}
                >
                    <Input
                        id="live_preview_url"
                        dir="ltr"
                        type="url"
                        placeholder="https://demo.example.com"
                        value={data.live_preview_url}
                        onChange={(e) => setData('live_preview_url', e.target.value)}
                    />
                </Field>
                <Field
                    label={__('Screenshots')}
                    htmlFor="screenshots"
                    error={errors.screenshots ?? errors.gallery}
                    hint={__('One image URL per line (up to 20).')}
                >
                    <textarea
                        id="screenshots"
                        dir="ltr"
                        value={data.screenshots}
                        onChange={(e) => setData('screenshots', e.target.value)}
                        rows={4}
                        placeholder="https://…/screenshot-1.png"
                        className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 font-mono text-xs shadow-sm focus-visible:ring-1 focus-visible:outline-hidden"
                    />
                </Field>
            </section>

            <section className="bg-card space-y-4 rounded-lg border p-6">
                <h2 className="text-base font-semibold">{__('Delivery')}</h2>

                <DownloadFileField
                    id="download_file"
                    label={data.type === 'subscription' ? __('Product file') : __('Product file — Regular License')}
                    description={__('The file buyers download after paying (zip, pdf…). It is stored privately — only buyers can download it.')}
                    upload={upload}
                    current={currentFile}
                    remove={data.remove_download_file}
                    onUploaded={(token) => {
                        setData('download_file_token', token ?? '');
                        if (token) setData('remove_download_file', false);
                    }}
                    onBusy={setUploadingRegular}
                    onRemove={(r) => setData('remove_download_file', r)}
                    error={errors.download_file ?? errors.download_file_token}
                />

                {data.type !== 'subscription' && (
                    <DownloadFileField
                        id="extended_file"
                        label={__('Extended License file (optional)')}
                        description={__(
                            'What Extended License buyers download — e.g. a version with extra rights or sources. Leave empty to give them the Regular License file.',
                        )}
                        upload={upload}
                        current={currentExtendedFile}
                        remove={Boolean(data.remove_extended_file)}
                        onUploaded={(token) => {
                            setData('extended_file_token', token ?? '');
                            if (token) setData('remove_extended_file', false);
                        }}
                        onBusy={setUploadingExtended}
                        onRemove={(r) => setData('remove_extended_file', r)}
                        error={errors.extended_file ?? errors.extended_file_token}
                    />
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Field label={__('Version')} htmlFor="version" error={errors.version}>
                        <Input id="version" value={data.version} onChange={(e) => setData('version', e.target.value)} />
                    </Field>
                    <Field
                        label={__('License type')}
                        htmlFor="license_type"
                        error={errors.license_type}
                        hint={__('e.g. single-site, unlimited, developer')}
                    >
                        <Input id="license_type" value={data.license_type} onChange={(e) => setData('license_type', e.target.value)} />
                    </Field>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field label={__('Default activation limit')} htmlFor="default_activation_limit" error={errors.default_activation_limit} required>
                        <Input
                            id="default_activation_limit"
                            type="number"
                            min={1}
                            value={data.default_activation_limit}
                            onChange={(e) => setData('default_activation_limit', Number(e.target.value))}
                            required
                        />
                    </Field>
                    <Field
                        label={__('Download limit')}
                        htmlFor="download_limit"
                        error={errors.download_limit}
                        hint={__('Leave blank for unlimited.')}
                    >
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

            <section className="bg-card space-y-4 rounded-lg border p-6">
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
                            <Checkbox id="is_featured" checked={data.is_featured} onCheckedChange={(v) => setData('is_featured', v === true)} />
                            <Label htmlFor="is_featured" className="cursor-pointer">
                                {__('Featured product')}
                            </Label>
                        </div>
                    )}
                </div>
            </section>

            <section className="bg-card space-y-4 rounded-lg border p-6">
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
                        className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:outline-hidden"
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
                {required && <span className="text-destructive ml-0.5">*</span>}
            </Label>
            {children}
            {hint && !error && <p className="text-muted-foreground text-xs">{hint}</p>}
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
    id,
    label,
    description,
    upload,
    current,
    remove,
    onUploaded,
    onBusy,
    onRemove,
    error,
}: {
    id: string;
    label: string;
    description: string;
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
            <Label htmlFor={id}>{label}</Label>
            <p className="text-muted-foreground text-xs">{description}</p>

            {chosen ? (
                <div className="border-primary/40 bg-primary/5 space-y-2 rounded-md border px-3 py-2 text-sm">
                    <div className="flex items-center justify-between gap-3">
                        <span className="flex min-w-0 items-center gap-2">
                            {state.status === 'done' ? (
                                <CheckCircle2 className="size-4 shrink-0 text-emerald-600" />
                            ) : direct ? (
                                <CloudUpload className="text-primary size-4 shrink-0" />
                            ) : (
                                <Upload className="text-primary size-4 shrink-0" />
                            )}
                            <span className="truncate" dir="ltr">
                                {chosen.name}
                            </span>
                            <span className="text-muted-foreground shrink-0 text-xs">{formatBytes(chosen.size)}</span>
                        </span>
                        <Button type="button" size="sm" variant="ghost" onClick={reset} title={__('Cancel')}>
                            <X />
                        </Button>
                    </div>
                    {state.status === 'uploading' && (
                        <div className="space-y-1">
                            <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                                <div className="bg-primary h-full rounded-full transition-all" style={{ width: `${state.percent}%` }} />
                            </div>
                            <p className="text-muted-foreground text-xs">{__('Uploading… :percent%', { percent: state.percent })}</p>
                        </div>
                    )}
                    {state.status === 'done' && (
                        <p className="text-xs text-emerald-700 dark:text-emerald-400">{__('Uploaded — save to attach it to the product.')}</p>
                    )}
                </div>
            ) : hasCurrent ? (
                <div className="bg-muted/30 flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm">
                    <span className="flex min-w-0 items-center gap-2">
                        <FileArchive className="text-muted-foreground size-4 shrink-0" />
                        <span className="truncate" dir="ltr">
                            {current?.name}
                        </span>
                        <span className="text-muted-foreground shrink-0 text-xs">{formatBytes(current?.size)}</span>
                    </span>
                    <Button type="button" size="sm" variant="ghost" className="text-destructive" onClick={() => onRemove(true)}>
                        {__('Remove')}
                    </Button>
                </div>
            ) : remove ? (
                <div className="text-muted-foreground flex items-center justify-between gap-3 rounded-md border border-dashed px-3 py-2 text-sm">
                    <span>{__('The current file will be removed when you save.')}</span>
                    <Button type="button" size="sm" variant="ghost" onClick={() => onRemove(false)}>
                        {__('Undo')}
                    </Button>
                </div>
            ) : null}

            {/* Re-mounted when the choice is cleared, so the native input empties too. */}
            {/* Our own button: the native one speaks the browser's language, not the site's. */}
            <label
                htmlFor={id}
                className="border-input bg-background hover:bg-muted/40 focus-within:ring-ring flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm transition-colors focus-within:ring-1"
            >
                <span className="bg-secondary text-secondary-foreground inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium">
                    <Upload className="size-3.5" /> {__('Choose a file')}
                </span>
                <span className="text-muted-foreground truncate">{chosen ? chosen.name : __('No file chosen')}</span>
                <input
                    key={chosen ? 'chosen' : 'empty'}
                    id={id}
                    type="file"
                    className="sr-only"
                    onChange={(e) => choose(e.target.files?.[0] ?? null)}
                />
            </label>
            <p className="text-muted-foreground text-xs">
                {direct
                    ? __('Uploaded straight to cloud storage — up to :size.', { size: maxLabel })
                    : __('Maximum file size: :size', { size: maxLabel })}
            </p>
            {hasCurrent && !chosen && <p className="text-muted-foreground text-xs">{__('Choose a new file to replace the current one.')}</p>}
            <InputError message={state.status === 'error' ? state.message : error} />
        </div>
    );
}

/** How the prices will look on the product page, updated as the seller types. */
function PricePreview({ data, licensing }: { data: ProductFormValues; licensing?: LicensingRule }) {
    const { __ } = useTranslate();
    const money = (value: string) => {
        const amount = parseFloat(value);
        if (!Number.isFinite(amount)) return null;
        try {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: data.currency || 'USD' }).format(amount);
        } catch {
            return `$${amount.toFixed(2)}`;
        }
    };

    const price = money(data.price);
    const sale = money(data.sale_price);
    const onSale = sale !== null && price !== null && parseFloat(data.sale_price) < parseFloat(data.price);
    const fallback = data.type === 'subscription' ? null : defaultExtended(data.price, licensing);
    const extended = money(data.extended_price) ?? (fallback !== null ? money(String(fallback)) : null);
    const support = money(data.support_extension_price);

    if (price === null) return null;

    return (
        <div className="bg-muted/30 rounded-md border border-dashed p-4">
            <p className="text-muted-foreground mb-2 text-xs font-medium">{__('On the product page')}</p>
            <div className="flex flex-wrap items-end justify-between gap-3">
                <span className="text-sm font-semibold">{__('Regular License')}</span>
                <span className="text-end">
                    <span className="block text-2xl font-bold tabular-nums">{onSale ? sale : price}</span>
                    {onSale && <span className="text-muted-foreground text-sm tabular-nums line-through">{price}</span>}
                </span>
            </div>
            {(extended || support) && (
                <ul className="text-muted-foreground mt-2 space-y-0.5 border-t pt-2 text-xs">
                    {extended && <li>{__('Extended License: :price', { price: extended })}</li>}
                    {support && <li>{__('Extend support to 12 months: +:price', { price: support })}</li>}
                </ul>
            )}
        </div>
    );
}
