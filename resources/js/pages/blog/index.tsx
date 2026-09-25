import { Button } from '@/components/ui/button';
import { Container } from '@/components/ui/container';
import { Input } from '@/components/ui/input';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type BlogPostSummary, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Filters {
    search: string;
    tag: string;
}

interface BlogIndexProps {
    posts: Paginated<BlogPostSummary>;
    tags: string[];
    filters: Filters;
}

export default function BlogIndex({ posts, tags, filters }: BlogIndexProps) {
    const { t, locale } = useTranslate();
    const [search, setSearch] = useState(filters.search);

    const applyFilter = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        for (const [key, value] of Object.entries(merged)) {
            if (value) params[key] = String(value);
        }
        router.get(route('blog.index'), params, { preserveScroll: true, preserveState: true, replace: true });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    return (
        <StorefrontLayout>
            <Head title={t('blog.meta_title')} />

            <Container className="py-12 sm:py-16">
                <header className="mx-auto flex max-w-2xl flex-col items-center gap-4 text-center">
                    <h1 className="font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{t('blog.title')}</h1>
                    <p className="text-muted-foreground text-pretty sm:text-lg">{t('blog.subtitle')}</p>
                </header>

                <form onSubmit={submitSearch} className="mx-auto mt-10 flex max-w-xl gap-2">
                    <Input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('blog.search_placeholder')}
                        aria-label={t('blog.search_placeholder')}
                    />
                    <Button type="submit" variant="secondary">
                        {t('blog.search_submit')}
                    </Button>
                </form>

                {tags.length > 0 && (
                    <nav className="mt-6 flex flex-wrap items-center justify-center gap-2" aria-label={t('blog.tags_label')}>
                        <TagPill active={filters.tag === ''} onClick={() => applyFilter({ tag: '' })}>
                            {t('blog.all_tags')}
                        </TagPill>
                        {tags.map((tag) => (
                            <TagPill key={tag} active={filters.tag === tag} onClick={() => applyFilter({ tag: filters.tag === tag ? '' : tag })}>
                                {tag}
                            </TagPill>
                        ))}
                    </nav>
                )}

                {posts.data.length === 0 ? (
                    <p className="text-muted-foreground mt-12 rounded-lg border border-dashed p-12 text-center">{t('blog.empty')}</p>
                ) : (
                    <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {posts.data.map((post) => (
                            <PostCard key={post.id} post={post} locale={locale} readMore={t('blog.read_more')} />
                        ))}
                    </div>
                )}

                {posts.last_page > 1 && (
                    <nav className="mt-10 flex flex-wrap items-center justify-center gap-1">
                        {posts.links.map((link, idx) => (
                            <PaginationLink key={idx} link={link} />
                        ))}
                    </nav>
                )}
            </Container>
        </StorefrontLayout>
    );
}

function PostCard({ post, locale, readMore }: { post: BlogPostSummary; locale: string; readMore: string }) {
    return (
        <article className="border-border/60 bg-card hover:border-border flex flex-col rounded-xl border p-5 transition-colors">
            {post.tags && post.tags.length > 0 && (
                <p className="text-muted-foreground font-mono text-[11px] tracking-wider uppercase">{post.tags[0]}</p>
            )}
            <h2 className="mt-2 text-lg font-semibold tracking-tight">
                <Link href={route('blog.show', post.slug)} className="hover:underline">
                    {post.title}
                </Link>
            </h2>
            {post.excerpt && <p className="text-muted-foreground mt-2 line-clamp-3 text-sm">{post.excerpt}</p>}
            <div className="text-muted-foreground mt-4 flex items-center gap-2 text-xs">
                {post.author && <span>{post.author.name}</span>}
                {post.author && post.published_at && <span aria-hidden>·</span>}
                {post.published_at && <time dateTime={post.published_at}>{formatDate(post.published_at, locale)}</time>}
            </div>
            <Link href={route('blog.show', post.slug)} className="text-primary mt-4 text-sm font-medium hover:underline">
                {readMore}
            </Link>
        </article>
    );
}

function TagPill({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={`rounded-full border px-3 py-1 text-xs transition ${
                active ? 'border-primary bg-primary text-primary-foreground' : 'border-input hover:bg-accent'
            }`}
        >
            {children}
        </button>
    );
}

function PaginationLink({ link }: { link: { url: string | null; label: string; active: boolean } }) {
    const className = `min-w-9 rounded-md border px-3 py-1.5 text-sm transition ${
        link.active
            ? 'border-primary bg-primary text-primary-foreground'
            : link.url
              ? 'border-input hover:bg-accent'
              : 'border-transparent text-muted-foreground'
    }`;

    if (!link.url) {
        return <span className={className} dangerouslySetInnerHTML={{ __html: link.label }} />;
    }

    return <Link href={link.url} preserveScroll preserveState className={className} dangerouslySetInnerHTML={{ __html: link.label }} />;
}

export function formatDate(value: string, locale: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    return new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'long', day: 'numeric' }).format(date);
}
