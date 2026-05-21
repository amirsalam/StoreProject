import { SVGAttributes } from 'react';

/**
 * StoreProject mark — a compact monogram with a hairline border.
 * Uses currentColor so it adopts text color in both themes.
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
            <rect
                x="1"
                y="1"
                width="22"
                height="22"
                rx="6"
                stroke="currentColor"
                strokeOpacity="0.18"
                strokeWidth="1"
            />
            <path
                d="M7.25 16.5V7.5h4.6c2.04 0 3.4 1.05 3.4 2.78 0 1.45-1.05 2.32-2.32 2.46l2.62 3.76h-2.06l-2.4-3.58H9v3.58H7.25Zm1.75-5h2.78c1.13 0 1.78-.43 1.78-1.3 0-.87-.65-1.3-1.78-1.3H9v2.6Z"
                fill="currentColor"
            />
        </svg>
    );
}
