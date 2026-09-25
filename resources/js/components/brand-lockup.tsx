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
    titleClassName,
    className,
}: BrandLockupProps) {
    const { branding } = usePage<SharedData>().props;
    const title = branding?.title ?? 'StoreProject';
    const hasCustomLogo = Boolean(branding?.has_custom_logo && branding?.logo_url);

    return (
        <span className={cn('inline-flex items-center gap-2 font-display text-sm font-semibold tracking-tight', className)}>
            <span
                className={cn(
                    'flex aspect-square items-center justify-center overflow-hidden rounded-md',
                    !hasCustomLogo && 'bg-foreground text-background',
                    iconClassName ?? 'size-7',
                )}
            >
                {hasCustomLogo ? (
                    <img
                        src={branding.logo_url!}
                        alt={title}
                        className="size-full object-contain"
                        loading="eager"
                        draggable={false}
                    />
                ) : (
                    <AppLogoIcon className="size-4" />
                )}
            </span>
            {!iconOnly && <span className={titleClassName}>{title}</span>}
        </span>
    );
}
