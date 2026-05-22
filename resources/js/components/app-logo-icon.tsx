import { SVGAttributes } from 'react';

/**
 * StoreProject mark — a friendly rounded shopping bag with a 4-point
 * spark inside, signifying "premium digital goods marketplace".
 *
 * Renders in currentColor so it adopts the surrounding text color in any
 * context (white-on-dark, dark-on-white, inverted CTA strip, etc.). For
 * the colorful brand mark used on marketing surfaces, see <AppLogoMark />
 * below.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            {...props}
        >
            {/* handles */}
            <path
                d="M8.5 8.25V6.5a3.5 3.5 0 0 1 7 0v1.75"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
            />
            {/* bag body */}
            <path
                d="M5.75 8.25h12.5l-1 11a2.25 2.25 0 0 1-2.24 2.05H8.99A2.25 2.25 0 0 1 6.75 19.25l-1-11Z"
                fill="currentColor"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinejoin="round"
            />
            {/* spark — drawn in the wrapper color (foreground) so it reads
                as a cutout in the bag. Works in both light/dark when this
                icon is rendered inside the standard bg-foreground box. */}
            <path
                d="M12 12l.62 1.65 1.65.62-1.65.62L12 16.54l-.62-1.65-1.65-.62 1.65-.62L12 12Z"
                className="fill-foreground"
            />
        </svg>
    );
}

/**
 * Brand-colored variant used on marketing surfaces (landing hero, CTA
 * strip, social cards). Renders with the indigo→fuchsia gradient
 * regardless of surrounding text color.
 */
export function AppLogoMark(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            {...props}
        >
            <defs>
                <linearGradient id="sp-brand-grad" x1="3" y1="2" x2="21" y2="22" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stopColor="#6366F1" />
                    <stop offset="55%" stopColor="#A855F7" />
                    <stop offset="100%" stopColor="#EC4899" />
                </linearGradient>
            </defs>
            <path
                d="M8.5 8.25V6.5a3.5 3.5 0 0 1 7 0v1.75"
                stroke="url(#sp-brand-grad)"
                strokeWidth="1.75"
                strokeLinecap="round"
            />
            <path
                d="M5.75 8.25h12.5l-1 11a2.25 2.25 0 0 1-2.24 2.05H8.99A2.25 2.25 0 0 1 6.75 19.25l-1-11Z"
                fill="url(#sp-brand-grad)"
                stroke="url(#sp-brand-grad)"
                strokeWidth="1.5"
                strokeLinejoin="round"
            />
            <path
                d="M12 12l.62 1.65 1.65.62-1.65.62L12 16.54l-.62-1.65-1.65-.62 1.65-.62L12 12Z"
                fill="white"
            />
        </svg>
    );
}
