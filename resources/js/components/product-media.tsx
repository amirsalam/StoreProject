import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useTranslate } from '@/hooks/use-translate';
import { cn } from '@/lib/utils';
import { type Product } from '@/types';
import { ChevronLeft, ChevronRight, ExternalLink, Images } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

/**
 * Product cover with "Live Preview" (opens the demo) and "Screenshots"
 * (a lightbox over the gallery) underneath, marketplace style.
 */
export default function ProductMedia({ product }: { product: Product }) {
    const { t } = useTranslate();
    const screenshots = (product.gallery ?? []).filter(Boolean);
    const [open, setOpen] = useState(false);
    const [index, setIndex] = useState(0);

    const show = (i: number) => {
        setIndex(i);
        setOpen(true);
    };

    return (
        <div className="space-y-3">
            <button
                type="button"
                onClick={() => screenshots.length > 0 && show(0)}
                className={cn(
                    'flex aspect-video w-full items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-muted to-muted/40 text-2xl text-muted-foreground',
                    screenshots.length > 0 ? 'cursor-zoom-in' : 'cursor-default',
                )}
            >
                {product.thumbnail ? (
                    <img src={product.thumbnail} alt={product.title} className="size-full object-cover" />
                ) : screenshots[0] ? (
                    <img src={screenshots[0]} alt={product.title} className="size-full object-cover" />
                ) : (
                    <span className="opacity-60">{product.title.slice(0, 2).toUpperCase()}</span>
                )}
            </button>

            {(product.live_preview_url || screenshots.length > 0) && (
                <div className="flex flex-wrap justify-center gap-3">
                    {product.live_preview_url && (
                        <Button asChild>
                            <a href={product.live_preview_url} target="_blank" rel="noopener noreferrer">
                                {t('product.live_preview')} <ExternalLink />
                            </a>
                        </Button>
                    )}
                    {screenshots.length > 0 && (
                        <Button type="button" variant="secondary" onClick={() => show(0)}>
                            {t('product.screenshots')} <Images />
                        </Button>
                    )}
                </div>
            )}

            {screenshots.length > 0 && <ScreenshotsDialog images={screenshots} title={product.title} open={open} onOpenChange={setOpen} index={index} setIndex={setIndex} />}
        </div>
    );
}

function ScreenshotsDialog({
    images,
    title,
    open,
    onOpenChange,
    index,
    setIndex,
}: {
    images: string[];
    title: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    index: number;
    setIndex: (i: number) => void;
}) {
    const { t, direction } = useTranslate();
    const go = useCallback((step: number) => setIndex((index + step + images.length) % images.length), [index, images.length, setIndex]);

    // Arrow keys follow reading direction.
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'ArrowRight') go(direction === 'rtl' ? -1 : 1);
            if (e.key === 'ArrowLeft') go(direction === 'rtl' ? 1 : -1);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, go, direction]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-5xl gap-3 p-3 sm:max-w-5xl">
                <DialogTitle className="px-1 text-sm font-medium">
                    {title} · {t('product.screenshot_count', { current: index + 1, total: images.length })}
                </DialogTitle>

                <div className="relative flex items-center justify-center overflow-hidden rounded-lg bg-muted">
                    <img src={images[index]} alt={`${title} ${index + 1}`} className="max-h-[70vh] w-auto object-contain" />
                    {images.length > 1 && (
                        <>
                            <Button type="button" size="icon" variant="secondary" className="absolute start-2 rounded-full shadow" onClick={() => go(-1)} aria-label={t('product.previous')}>
                                <ChevronLeft className="rtl:rotate-180" />
                            </Button>
                            <Button type="button" size="icon" variant="secondary" className="absolute end-2 rounded-full shadow" onClick={() => go(1)} aria-label={t('product.next')}>
                                <ChevronRight className="rtl:rotate-180" />
                            </Button>
                        </>
                    )}
                </div>

                {images.length > 1 && (
                    <div className="flex gap-2 overflow-x-auto pb-1">
                        {images.map((src, i) => (
                            <button
                                key={src + i}
                                type="button"
                                onClick={() => setIndex(i)}
                                className={cn('h-14 w-24 shrink-0 overflow-hidden rounded-md border-2', i === index ? 'border-primary' : 'border-transparent opacity-70 hover:opacity-100')}
                            >
                                <img src={src} alt="" className="size-full object-cover" />
                            </button>
                        ))}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
