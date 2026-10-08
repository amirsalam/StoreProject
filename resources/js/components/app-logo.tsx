import BrandLockup from './brand-lockup';

/**
 * Sidebar/header brand block. Reads the live branding (title + optional
 * custom logo) from the Inertia-shared prop via BrandLockup.
 */
export default function AppLogo() {
    return (
        <>
            <BrandLockup
                iconOnly
                iconClassName="size-8"
                className="font-display text-sm font-semibold"
            />
            <div className="ms-1 grid flex-1 text-left text-sm">
                <BrandTitleOnly />
            </div>
        </>
    );
}

import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

function BrandTitleOnly() {
    const { branding } = usePage<SharedData>().props;
    return (
        <span className="mb-0.5 truncate font-display font-semibold leading-none tracking-tight">
            {branding?.title ?? 'StoreProject'}
        </span>
    );
}
