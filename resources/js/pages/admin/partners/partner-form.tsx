import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/hooks/use-translate';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ImageIcon, Loader2, Trash2, UploadCloud } from 'lucide-react';
import { ChangeEvent, FormEvent, useEffect, useRef, useState } from 'react';

export interface Partner {
    id: number;
    name: string;
    logo_path: string | null;
    logo_url: string | null;
    website_url: string | null;
    sort_order: number;
    is_active: boolean;
}

export interface PartnerFormData {
    name: string;
    website_url: string;
    is_active: boolean;
    logo: File | null;
    remove_logo: boolean;
    [key: string]: string | boolean | File | null;
}

export const ACCEPTED_MIMES = 'image/png,image/jpeg,image/svg+xml,image/webp';
const MAX_BYTES = 2 * 1024 * 1024;

interface Props {
    data: PartnerFormData;
    setData: <K extends keyof PartnerFormData>(key: K, value: PartnerFormData[K]) => void;
    errors: Partial<Record<keyof PartnerFormData, string>>;
    processing: boolean;
    onSubmit: (e: FormEvent) => void;
    submitLabel: string;
    /** Logo already saved on the partner (edit only). */
    currentLogoUrl?: string | null;
}

/**
 * Shared create / edit form for a homepage partner logo.
 */
export default function PartnerForm({ data, setData, errors, processing, onSubmit, submitLabel, currentLogoUrl = null }: Props) {
    const { __ } = useTranslate();
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [localError, setLocalError] = useState<string | null>(null);

    useEffect(
        () => () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
        },
        [previewUrl],
    );

    const onFile = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setLocalError(null);
        if (!file) return;
        if (file.size > MAX_BYTES) {
            setLocalError(__('Logo must be under 2 MB.'));
            e.target.value = '';
            return;
        }
        if (!ACCEPTED_MIMES.split(',').includes(file.type)) {
            setLocalError(__('Logo must be PNG, JPG, SVG, or WebP.'));
            e.target.value = '';
            return;
        }
        setData('logo', file);
        setData('remove_logo', false);
        setPreviewUrl(URL.createObjectURL(file));
    };

    const clearLogo = () => {
        setData('logo', null);
        setPreviewUrl(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
        if (currentLogoUrl) setData('remove_logo', true);
    };

    const shownLogo = previewUrl ?? (data.remove_logo ? null : currentLogoUrl);

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <section className="border-border bg-card space-y-5 rounded-xl border p-6 shadow-sm sm:p-8">
                <div className="space-y-1.5">
                    <Label htmlFor="name">{__('Name')}</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        maxLength={80}
                        required
                        autoComplete="off"
                        placeholder="Laravel"
                    />
                    <p className="text-muted-foreground text-xs">{__('Shown as text when there is no logo, and as the logo’s alt text.')}</p>
                    <InputError message={errors.name} />
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="website_url">{__('Website (optional)')}</Label>
                    <Input
                        id="website_url"
                        type="url"
                        value={data.website_url}
                        onChange={(e) => setData('website_url', e.target.value)}
                        placeholder="https://laravel.com"
                        autoComplete="off"
                    />
                    <p className="text-muted-foreground text-xs">{__('When set, the logo links to this address.')}</p>
                    <InputError message={errors.website_url} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="logo">{__('Logo (optional)')}</Label>
                    <div className="grid gap-4 sm:grid-cols-[180px_1fr]">
                        <div
                            className={cn(
                                'bg-muted/30 flex h-24 items-center justify-center overflow-hidden rounded-lg border p-3',
                                previewUrl && 'border-primary/50 bg-primary/[0.04]',
                            )}
                        >
                            {shownLogo ? (
                                <img src={shownLogo} alt={data.name} className="max-h-full max-w-full object-contain" />
                            ) : data.name ? (
                                <span className="font-display text-muted-foreground truncate text-sm font-semibold tracking-[0.2em] uppercase">
                                    {data.name}
                                </span>
                            ) : (
                                <ImageIcon className="text-muted-foreground/50 size-7" />
                            )}
                        </div>
                        <div className="space-y-2">
                            <label
                                htmlFor="logo"
                                className="border-border bg-muted/20 hover:bg-muted/40 flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed p-4 text-sm transition-colors"
                            >
                                <UploadCloud className="text-muted-foreground size-5" />
                                <span>
                                    <span className="text-primary font-medium">{__('Click to upload')}</span>
                                    <span className="text-muted-foreground block text-xs">{__('PNG · JPG · SVG · WebP · up to 2 MB')}</span>
                                </span>
                                <input id="logo" ref={fileInputRef} type="file" accept={ACCEPTED_MIMES} onChange={onFile} className="hidden" />
                            </label>
                            {(previewUrl || (currentLogoUrl && !data.remove_logo)) && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={clearLogo}
                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                >
                                    <Trash2 />
                                    {__('Remove logo')}
                                </Button>
                            )}
                            {data.remove_logo && !previewUrl && (
                                <p className="text-muted-foreground text-xs">{__('The logo will be removed when you save.')}</p>
                            )}
                        </div>
                    </div>
                    <InputError message={localError ?? errors.logo} />
                </div>

                <label className="flex items-center gap-3 text-sm">
                    <Checkbox checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                    <span>
                        <span className="font-medium">{__('Show on the homepage')}</span>
                        <span className="text-muted-foreground block text-xs">{__('Hidden partners stay saved but are not displayed.')}</span>
                    </span>
                </label>
            </section>

            <div className="flex flex-wrap items-center justify-end gap-2">
                <Button asChild variant="ghost">
                    <Link href={route('admin.partners.index')}>{__('Cancel')}</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Loader2 className="animate-spin" />}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
