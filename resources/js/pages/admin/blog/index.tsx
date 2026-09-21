import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BlogPost, type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Option {
    value: string;
    label: string;
}

interface Filters {
    search: string;
    status: string;
}

interface AdminBlogIndexProps {
    posts: Paginated<BlogPost>;
    filters: Filters;
    statuses: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Blog', href: '/admin/blog-posts' },
];

export default function AdminBlogIndex({ posts, filters, statuses }: AdminBlogIndexProps) {
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const [search, setSearch] = useState(filters.search);
    const { delete: destroy, processing } = useForm({});

    const applyFilter = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries(merged)) {
            if (v) params[k] = String(v);
        }
        router.get(route('admin.blog-posts.index'), params, { preserveScroll: true, preserveState: true, replace: true });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    const handleDelete = (post: BlogPost) => {
        if (!confirm(`Delete "${post.title}"? This cannot be undone.`)) return;
        destroy(route('admin.blog-posts.destroy', post.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin · Blog" />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Blog</h1>
                        <p className="text-muted-foreground text-sm">
                            {posts.total} {posts.total === 1 ? 'post' : 'posts'} total
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.blog-posts.create')}>
                            <Plus className="mr-1" /> New post
                        </Link>
                    </Button>
                </div>

                <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <Input
                        type="search"
                        placeholder="Search by title or slug…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full sm:w-64"
                    />
                    <select
                        value={filters.status}
                        onChange={(e) => applyFilter({ status: e.target.value })}
                        className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">All statuses</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                </form>

                {/* Desktop / tablet: data table */}
                <div className="bg-card hidden overflow-hidden rounded-lg border md:block">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs tracking-wider uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Title</th>
                                <th className="px-4 py-3 font-medium">Author</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Published</th>
                                <th className="px-4 py-3 font-medium">Views</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {posts.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-12 text-center">
                                        No posts match these filters.
                                    </td>
                                </tr>
                            ) : (
                                posts.data.map((post) => (
                                    <tr key={post.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <div className="font-medium">{post.title}</div>
                                            <div className="text-muted-foreground text-xs">{post.slug}</div>
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">{post.author?.name ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={post.status} />
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">{formatDate(post.published_at)}</td>
                                        <td className="text-muted-foreground px-4 py-3 tabular-nums">{post.views_count}</td>
                                        <td className="px-4 py-3 text-right">
                                            <RowActions post={post} onDelete={handleDelete} processing={processing} />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Mobile: stacked card list */}
                <div className="space-y-3 md:hidden">
                    {posts.data.length === 0 ? (
                        <div className="bg-card text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">
                            No posts match these filters.
                        </div>
                    ) : (
                        posts.data.map((post) => (
                            <div key={post.id} className="bg-card space-y-2 rounded-lg border p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="font-medium">{post.title}</div>
                                        <div className="text-muted-foreground text-xs">{post.slug}</div>
                                    </div>
                                    <StatusBadge status={post.status} />
                                </div>
                                <div className="text-muted-foreground text-xs">
                                    {post.author?.name ?? '—'} · {formatDate(post.published_at)} · {post.views_count} views
                                </div>
                                <RowActions post={post} onDelete={handleDelete} processing={processing} />
                            </div>
                        ))
                    )}
                </div>

                {posts.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-center gap-1">
                        {posts.links.map((link, idx) =>
                            link.url ? (
                                <Link
                                    key={idx}
                                    href={link.url}
                                    preserveScroll
                                    preserveState
                                    className={`min-w-9 rounded-md border px-3 py-1.5 text-sm ${
                                        link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-input hover:bg-accent'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={idx}
                                    className="text-muted-foreground min-w-9 rounded-md border border-transparent px-3 py-1.5 text-sm"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}

function RowActions({ post, onDelete, processing }: { post: BlogPost; onDelete: (post: BlogPost) => void; processing: boolean }) {
    return (
        <div className="flex justify-end gap-1">
            {post.status === 'published' && (
                <Button asChild size="sm" variant="ghost" title="View on site">
                    <a href={route('blog.show', post.slug)} target="_blank" rel="noopener noreferrer">
                        <ExternalLink />
                    </a>
                </Button>
            )}
            <Button asChild size="sm" variant="ghost" title="Edit">
                <Link href={route('admin.blog-posts.edit', post.id)}>
                    <Pencil />
                </Link>
            </Button>
            <Button
                size="sm"
                variant="ghost"
                onClick={() => onDelete(post)}
                disabled={processing}
                title="Delete"
                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
            >
                <Trash2 />
            </Button>
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    return <Badge variant={status === 'published' ? 'default' : 'secondary'}>{status === 'published' ? 'Published' : 'Draft'}</Badge>;
}

function formatDate(value: string | null): string {
    if (!value) return '—';
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString();
}
