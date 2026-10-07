import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';
import { installLocalizedValidation } from './lib/localized-validation';

declare global {
    const route: typeof routeFn;
}

// Tab title suffix: the site title from Admin → Branding (kept current on
// every visit), falling back to APP_NAME before the first page loads.
let appName: string = import.meta.env.VITE_APP_NAME || 'StoreProject';
const brandTitle = (props: Record<string, unknown>) => (props.branding as { title?: string } | undefined)?.title;

createInertiaApp({
    title: (title) => (!title ? appName : title.includes(appName) ? title : `${title} - ${appName}`),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        appName = brandTitle(props.initialPage.props) || appName;

        // Browser form-check messages in the site's language: keep the
        // current page's phrases (lang/{locale}.json) at hand.
        let phrases = (props.initialPage.props.phrases ?? {}) as Record<string, string>;
        router.on('navigate', (event) => {
            phrases = (event.detail.page.props.phrases ?? {}) as Record<string, string>;
            appName = brandTitle(event.detail.page.props) || appName;
        });
        installLocalizedValidation(() => (text, replacements) => {
            const value = phrases[text] ?? text;
            return replacements ? value.replace(/:(\w+)/g, (m, key) => (key in replacements ? String(replacements[key]) : m)) : value;
        });

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
