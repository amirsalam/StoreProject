import { useTranslate } from '@/hooks/use-translate';
import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

interface AppLicenseNotice {
    state: 'not_activated' | 'grace' | 'unlicensed';
    reason: string | null;
    grace_ends_at: string | null;
}

/**
 * Admin-only notice (the server sends `app_license` only to admins, and only
 * when the installation is not fully licensed). Hidden on the Installation
 * license page itself, which already explains everything.
 */
export function AppLicenseBanner() {
    const { __ } = useTranslate();
    const { app_license: notice } = usePage<{ app_license?: AppLicenseNotice | null }>().props;
    const { url } = usePage();

    if (!notice || url.startsWith('/admin/app-license')) {
        return null;
    }

    const message =
        notice.state === 'grace'
            ? __('The license server could not be reached to renew the license. The installation keeps working until :date.', {
                  date: notice.grace_ends_at
                      ? new Date(notice.grace_ends_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
                      : '—',
              })
            : __('This installation is not licensed. Admin areas, vendor workspaces and checkout are disabled until it is activated.');

    return (
        <div
            role="alert"
            className="mx-4 mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300"
        >
            <AlertTriangle className="size-4 shrink-0" />
            <span className="flex-1">{message}</span>
            <Link href="/admin/app-license" className="font-medium underline underline-offset-4">
                {__('Installation license')}
            </Link>
        </div>
    );
}
