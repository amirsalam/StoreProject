import { type PaginatedLink } from '@/types';
import { Link } from '@inertiajs/react';

/** Laravel paginator links (labels are the paginator's own HTML entities). */
export default function PaginationLinks({ links }: { links: PaginatedLink[] }) {
    return (
        <nav className="flex flex-wrap items-center justify-center gap-1">
            {links.map((link, idx) => {
                const className = `min-w-9 rounded-md border px-3 py-1.5 text-sm transition ${
                    link.active
                        ? 'border-primary bg-primary text-primary-foreground'
                        : link.url
                          ? 'border-input hover:bg-accent'
                          : 'border-transparent text-muted-foreground'
                }`;

                return link.url ? (
                    <Link key={idx} href={link.url} preserveScroll preserveState className={className} dangerouslySetInnerHTML={{ __html: link.label }} />
                ) : (
                    <span key={idx} className={className} dangerouslySetInnerHTML={{ __html: link.label }} />
                );
            })}
        </nav>
    );
}
