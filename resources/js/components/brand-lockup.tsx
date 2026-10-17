import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

interface BrandLockupProps {
    /**
     * When true, renders only the logo mark (no wordmark). Useful in
     * tight contexts like the storefront top bar.
     */
    iconOnly?: boolean;
    /**
     * Override Tailwind size for the icon. Defaults to size-7 inside the
     * standard 28px box.
     */
    iconClassName?: string;
    /**
     * Height class for an uploaded logo image (width follows its aspect
     * ratio). Defaults to h-10.
     */
    logoClassName?: string;
    /**
     * Wordmark text class — defaults to a small display-font label.
     */
    titleClassName?: string;
    className?: string;
}

/**
 * Single source of truth for the brand mark + title across the app.
 *
 * Reads the live branding from the Inertia-shared `branding` prop and
 * renders either the uploaded logo image (when an admin has uploaded
 * one) or the default <AppLogoIcon /> mark inside the brand box.
 */
export default function BrandLockup({
    iconOnly = false,
    iconClassName,
    logoClassName,
    titleClassName,
    className,
}: BrandLockupProps) {
    const { branding } = usePage<SharedData>().props;
    const title = branding?.title ?? 'StoreProject';
    const hasCustomLogo = Boolean(branding?.has_custom_logo && branding?.logo_url);

    return (
        <span className={cn('inline-flex items-center gap-2 font-display text-sm font-semibold tracking-tight', className)}>
            {hasCustomLogo ? (
                // Uploaded logos keep their own aspect ratio: fixed height,
                // natural width (capped), so wide marks aren't squashed into a square.
                <img
                    src={branding.logo_url!}
                    alt={title}
                    className={cn('w-auto max-w-44 shrink-0 object-contain', logoClassName ?? 'h-10')}
                    loading="eager"
                    draggable={false}
                />
            ) : (
                <span
                    className={cn(
                        'flex aspect-square items-center justify-center overflow-hidden rounded-md bg-foreground text-background',
                        iconClassName ?? 'size-7',
                    )}
                >
                    <AppLogoIcon className="size-4" />
                </span>
            )}
            {!iconOnly && <span className={titleClassName}>{title}</span>}
        </span>
    );
}
