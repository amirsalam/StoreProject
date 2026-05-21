import { cn } from '@/lib/utils';
import * as React from 'react';

export const Container = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
    ({ className, ...props }, ref) => (
        <div ref={ref} className={cn('mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8', className)} {...props} />
    ),
);
Container.displayName = 'Container';

export function Section({
    className,
    children,
    bleed = false,
    ...props
}: React.HTMLAttributes<HTMLElement> & { bleed?: boolean }) {
    return (
        <section className={cn('relative', !bleed && 'py-20 sm:py-28', className)} {...props}>
            {children}
        </section>
    );
}

export function Eyebrow({ className, children, ...props }: React.HTMLAttributes<HTMLSpanElement>) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/[0.06] px-2.5 py-1 font-mono text-[11px] uppercase tracking-wider text-primary backdrop-blur dark:bg-primary/10',
                className,
            )}
            {...props}
        >
            {children}
        </span>
    );
}

export function SectionHeading({
    eyebrow,
    title,
    description,
    align = 'center',
    className,
}: {
    eyebrow?: React.ReactNode;
    title: React.ReactNode;
    description?: React.ReactNode;
    align?: 'center' | 'left';
    className?: string;
}) {
    return (
        <div
            className={cn(
                'mx-auto flex max-w-2xl flex-col gap-4',
                align === 'center' ? 'items-center text-center' : 'items-start text-left',
                className,
            )}
        >
            {eyebrow && <Eyebrow>{eyebrow}</Eyebrow>}
            <h2 className="text-balance font-display text-3xl font-semibold tracking-tight sm:text-4xl lg:text-[44px] lg:leading-[1.05]">
                {title}
            </h2>
            {description && (
                <p className="text-pretty text-base text-muted-foreground sm:text-lg">{description}</p>
            )}
        </div>
    );
}
