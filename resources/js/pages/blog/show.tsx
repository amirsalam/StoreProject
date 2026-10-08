import { Container } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { formatDate } from '@/pages/blog/index';
import { type BlogPost, type BlogPostSummary } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';

interface BlogShowProps {
    post: BlogPost;
    /** Markdown rendered server-side with raw HTML stripped (see BlogController). */
    contentHtml: string;
    readingMinutes: number;
    related: BlogPostSummary[];
}

/** Typography for the rendered Markdown — no typography plugin in this project. */
const PROSE = [
    'max-w-none text-base/7',
    '[&_p]:my-5 [&_p]:text-foreground/90',
    '[&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight',
    '[&_h3]:mt-8 [&_h3]:mb-2 [&_h3]:text-xl [&_h3]:font-semibold',
    '[&_ul]:my-5 [&_ul]:list-disc [&_ul]:ps-6 [&_ol]:my-5 [&_ol]:list-decimal [&_ol]:ps-6 [&_li]:my-1',
    '[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4',
    '[&_blockquote]:my-6 [&_blockquote]:border-s-2 [&_blockquote]:border-primary/40 [&_blockquote]:ps-4 [&_blockquote]:text-muted-foreground [&_blockquote]:italic',
    '[&_code]:rounded [&_code]:bg-muted [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.875em]',
    '[&_pre]:my-6 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:border [&_pre]:bg-muted [&_pre]:p-4',
    '[&_pre_code]:bg-transparent [&_pre_code]:p-0',
    '[&_img]:my-6 [&_img]:rounded-lg',
    '[&_hr]:my-10 [&_hr]:border-border',
].join(' ');

export default function BlogShow({ post, contentHtml, readingMinutes, related }: BlogShowProps) {
    const { t, locale, direction } = useTranslate();
    const BackArrow = direction === 'rtl' ? ArrowRight : ArrowLeft;

    return (
        <StorefrontLayout>
            <Head title={post.seo_title ?? post.title}>
                {post.seo_description && <meta name="description" content={post.seo_description} />}
                {post.excerpt && !post.seo_description && <meta name="description" content={post.excerpt} />}
            </Head>

            <Container className="py-12 sm:py-16">
                <div className="mx-auto max-w-3xl">
                    <Link href={route('blog.index')} className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm">
                        <BackArrow className="size-4" />
                        {t('blog.back_to_index')}
                    </Link>

                    <article className="mt-6">
                        <header className="flex flex-col gap-4">
                            {post.tags && post.tags.length > 0 && (
                                <div className="flex flex-wrap gap-2">
                                    {post.tags.map((tag) => (
                                        <Link
                                            key={tag}
                                            href={route('blog.index', { tag })}
                                            className="border-input hover:bg-accent rounded-full border px-3 py-1 text-xs"
                                        >
                                            {tag}
                                        </Link>
                                    ))}
                                </div>
                            )}

                            <h1 className="font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{post.title}</h1>

                            <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-sm">
                                {post.author && <span>{post.author.name}</span>}
                                {post.author && post.published_at && <span aria-hidden>·</span>}
                                {post.published_at && <time dateTime={post.published_at}>{formatDate(post.published_at, locale)}</time>}
                                <span aria-hidden>·</span>
                                <span>{t('blog.reading_time', { minutes: readingMinutes })}</span>
                            </div>
                        </header>

                        {/*
                          contentHtml is Markdown rendered server-side with html_input=strip
                          and allow_unsafe_links=false, so no author-supplied markup or
                          javascript: URL can reach the DOM here.
                        */}
                        <div className={`mt-8 ${PROSE}`} dangerouslySetInnerHTML={{ __html: contentHtml }} />
                    </article>

                    {related.length > 0 && (
                        <section className="border-border/60 mt-16 border-t pt-8">
                            <h2 className="font-display text-xl font-semibold tracking-tight">{t('blog.related')}</h2>
                            <ul className="mt-4 space-y-3">
                                {related.map((item) => (
                                    <li key={item.id}>
                                        <Link href={route('blog.show', item.slug)} className="group block">
                                            <span className="font-medium group-hover:underline">{item.title}</span>
                                            {item.published_at && (
                                                <time dateTime={item.published_at} className="text-muted-foreground ms-2 text-xs">
                                                    {formatDate(item.published_at, locale)}
                                                </time>
                                            )}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </div>
            </Container>
        </StorefrontLayout>
    );
}
