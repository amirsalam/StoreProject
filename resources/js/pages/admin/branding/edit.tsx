import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BrandingSummary, type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Image as ImageIcon, ImageUp, Loader2, Trash2, UploadCloud } from 'lucide-react';
import { ChangeEvent, DragEvent, FormEvent, useRef, useState } from 'react';

interface AdminBrandingEditProps {
    branding: BrandingSummary;
}

interface FormShape {
    title: string;
    logo: File | null;
    [key: string]: string | number | boolean | File | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Branding', href: '/admin/branding' },
];

const ACCEPTED_MIMES = 'image/png,image/jpeg,image/svg+xml,image/webp';
const MAX_BYTES = 2 * 1024 * 1024;

export default function AdminBrandingEdit({ branding }: AdminBrandingEditProps) {
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm<FormShape>({
        title: branding.title,
        logo: null,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setLocalError(null);
        post(route('admin.branding.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset('logo');
                setPreviewUrl(null);
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    const validateAndSet = (file: File | null) => {
        setLocalError(null);
        if (!file) {
            setData('logo', null);
            setPreviewUrl(null);
            return;
        }
        if (file.size > MAX_BYTES) {
            setLocalError('Logo must be under 2 MB.');
            return;
        }
        const ok = ACCEPTED_MIMES.split(',').includes(file.type);
        if (!ok) {
            setLocalError('Logo must be PNG, JPG, SVG, or WebP.');
            return;
        }
        setData('logo', file);
        const url = URL.createObjectURL(file);
        setPreviewUrl((prev) => {
            if (prev) URL.revokeObjectURL(prev);
            return url;
        });
    };

    const onFile = (e: ChangeEvent<HTMLInputElement>) => {
        validateAndSet(e.target.files?.[0] ?? null);
    };

    const onDrop = (e: DragEvent<HTMLLabelElement>) => {
        e.preventDefault();
        setDragging(false);
        validateAndSet(e.dataTransfer.files?.[0] ?? null);
    };

    const removeStagedLogo = () => {
        setData('logo', null);
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        setPreviewUrl(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const deleteSavedLogo = () => {
        if (!confirm('Remove the current logo and revert to the default mark?')) return;
        router.delete(route('admin.branding.logo.destroy'), { preserveScroll: true });
    };

    const previewSrc = previewUrl ?? branding.logo_url;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Branding · Admin" />

            <div className="mx-auto w-full max-w-4xl space-y-6 px-4 py-6 sm:py-8">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">Branding</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Customize the site title and logo. Changes apply across the storefront, dashboard, and auth pages.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-6">
                    {/* TITLE */}
                    <section className="space-y-4 rounded-xl border border-border bg-card p-6 shadow-sm sm:p-8">
                        <header className="space-y-1">
                            <h2 className="font-display text-base font-semibold tracking-tight">Site title</h2>
                            <p className="text-sm text-muted-foreground">
                                Shown next to the logo in the header, footer, browser tab, and emails.
                            </p>
                        </header>

                        <div className="space-y-1.5">
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                maxLength={60}
                                required
                                autoComplete="off"
                            />
                            <div className="flex items-center justify-between">
                                <InputError message={errors.title} />
                                <span className="ms-auto font-mono text-[11px] text-muted-foreground">
                                    {data.title.length}/60
                                </span>
                            </div>
                        </div>
                    </section>

                    {/* LOGO */}
                    <section className="space-y-5 rounded-xl border border-border bg-card p-6 shadow-sm sm:p-8">
                        <header className="space-y-1">
                            <h2 className="font-display text-base font-semibold tracking-tight">Logo</h2>
                            <p className="text-sm text-muted-foreground">
                                PNG, JPG, SVG, or WebP. Maximum 2 MB. Transparent backgrounds work best.
                            </p>
                        </header>

                        <div className="grid gap-5 sm:grid-cols-[200px_1fr]">
                            {/* Preview card */}
                            <div className="space-y-2">
                                <div className="font-mono text-[11px] uppercase tracking-wider text-muted-foreground">
                                    {previewUrl ? 'Preview (unsaved)' : 'Current logo'}
                                </div>
                                <div
                                    className={cn(
                                        'relative flex aspect-square w-full items-center justify-center overflow-hidden rounded-lg border',
                                        previewUrl
                                            ? 'border-primary/50 bg-primary/[0.04]'
                                            : 'border-border bg-muted/30',
                                    )}
                                >
                                    {previewSrc ? (
                                        <img
                                            src={previewSrc}
                                            alt="Logo preview"
                                            className="size-3/4 object-contain"
                                        />
                                    ) : (
                                        <div className="flex flex-col items-center gap-2 text-center text-muted-foreground">
                                            <ImageIcon className="size-8 opacity-50" />
                                            <span className="text-xs">Default mark</span>
                                        </div>
                                    )}
                                </div>
                                {branding.has_custom_logo && !previewUrl && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={deleteSavedLogo}
                                        className="w-full text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    >
                                        <Trash2 />
                                        Remove logo
                                    </Button>
                                )}
                            </div>

                            {/* Dropzone */}
                            <label
                                htmlFor="logo"
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={onDrop}
                                className={cn(
                                    'flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed p-8 text-center transition-colors',
                                    dragging
                                        ? 'border-primary bg-primary/[0.06]'
                                        : 'border-border bg-muted/20 hover:bg-muted/40',
                                )}
                            >
                                <UploadCloud
                                    className={cn(
                                        'size-8 transition-colors',
                                        dragging ? 'text-primary' : 'text-muted-foreground',
                                    )}
                                />
                                <div className="space-y-1">
                                    <div className="text-sm font-medium">
                                        <span className="text-primary">Click to upload</span> or drag & drop
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        PNG · JPG · SVG · WebP · up to 2 MB
                                    </p>
                                </div>
                                {data.logo && (
                                    <div className="mt-2 flex items-center gap-2 rounded-md border border-border bg-background px-3 py-1.5 text-xs">
                                        <ImageUp className="size-3.5 text-primary" />
                                        <span className="max-w-[180px] truncate font-medium">
                                            {data.logo.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {formatBytes(data.logo.size)}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                removeStagedLogo();
                                            }}
                                            className="ms-1 text-muted-foreground hover:text-destructive"
                                            aria-label="Clear staged logo"
                                        >
                                            ×
                                        </button>
                                    </div>
                                )}
                                <input
                                    id="logo"
                                    ref={fileInputRef}
                                    type="file"
                                    accept={ACCEPTED_MIMES}
                                    onChange={onFile}
                                    className="hidden"
                                />
                            </label>
                        </div>

                        {(errors.logo || localError) && (
                            <p className="text-sm text-destructive">{localError ?? errors.logo}</p>
                        )}
                    </section>

                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <Button type="submit" disabled={processing} size="lg">
                            {processing ? (
                                <>
                                    <Loader2 className="animate-spin" />
                                    Saving…
                                </>
                            ) : (
                                <>Save changes</>
                            )}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
}
