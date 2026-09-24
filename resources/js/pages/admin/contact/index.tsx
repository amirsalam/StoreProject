import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type ContactMessage, type Paginated } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Archive, MailOpen, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Option {
    value: string;
    label: string;
}

interface Filters {
    search: string;
    status: string;
}

interface AdminContactIndexProps {
    messages: Paginated<ContactMessage>;
    filters: Filters;
    statuses: Option[];
    unreadCount: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Contact', href: '/admin/contact' },
];

export default function AdminContactIndex({ messages, filters, statuses, unreadCount }: AdminContactIndexProps) {
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const [search, setSearch] = useState(filters.search);
    const { delete: destroy, processing } = useForm({});

    const applyFilter = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries(merged)) {
            if (v) params[k] = String(v);
        }
        router.get(route('admin.contact.index'), params, { preserveScroll: true, preserveState: true, replace: true });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    const setStatus = (message: ContactMessage, status: string) => {
        router.patch(route('admin.contact.update', message.id), { status }, { preserveScroll: true });
    };

    const handleDelete = (message: ContactMessage) => {
        if (!confirm(`Delete the message from ${message.name}? This cannot be undone.`)) return;
        destroy(route('admin.contact.destroy', message.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin · Contact" />

            <div className="mx-auto w-full max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Contact inbox</h1>
                        <p className="text-muted-foreground text-sm">
                            {messages.total} {messages.total === 1 ? 'message' : 'messages'}
                            {unreadCount > 0 && ` · ${unreadCount} unread`}
                        </p>
                    </div>
                </div>

                <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <Input
                        type="search"
                        placeholder="Search name, email or subject…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full sm:w-72"
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

                {messages.data.length === 0 ? (
                    <div className="bg-card text-muted-foreground rounded-lg border border-dashed p-12 text-center text-sm">
                        No messages match these filters.
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {messages.data.map((message) => (
                            <li key={message.id} className="bg-card space-y-3 rounded-lg border p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="font-medium">{message.subject}</h2>
                                            <StatusBadge status={message.status} />
                                        </div>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {message.name} ·{' '}
                                            <a href={`mailto:${message.email}`} className="hover:underline">
                                                {message.email}
                                            </a>{' '}
                                            · {new Date(message.created_at).toLocaleString()}
                                        </p>
                                    </div>
                                    <div className="flex gap-1">
                                        {message.status === 'new' && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                title="Mark as read"
                                                disabled={processing}
                                                onClick={() => setStatus(message, 'read')}
                                            >
                                                <MailOpen />
                                            </Button>
                                        )}
                                        {message.status !== 'archived' && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                title="Archive"
                                                disabled={processing}
                                                onClick={() => setStatus(message, 'archived')}
                                            >
                                                <Archive />
                                            </Button>
                                        )}
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            title="Delete"
                                            disabled={processing}
                                            onClick={() => handleDelete(message)}
                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </div>

                                <p className="text-foreground/90 text-sm whitespace-pre-wrap">{message.message}</p>

                                <div>
                                    <Button asChild size="sm" variant="outline">
                                        <a href={`mailto:${message.email}?subject=${encodeURIComponent(`Re: ${message.subject}`)}`}>Reply by email</a>
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {messages.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-center gap-1">
                        {messages.links.map((link, idx) =>
                            link.url ? (
                                <button
                                    key={idx}
                                    type="button"
                                    onClick={() => router.get(link.url as string, {}, { preserveScroll: true, preserveState: true })}
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

function StatusBadge({ status }: { status: string }) {
    if (status === 'new') return <Badge>New</Badge>;
    if (status === 'archived') return <Badge variant="outline">Archived</Badge>;

    return <Badge variant="secondary">Read</Badge>;
}
