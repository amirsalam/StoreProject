import { useConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Calendar, FolderKanban, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Project {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    status: 'active' | 'paused' | 'archived';
    starts_on: string | null;
    due_on: string | null;
    tasks_count: number;
    owner?: { id: number; name: string } | null;
    created_at: string;
}

interface Option {
    value: string;
    label: string;
}

interface Filters {
    search: string;
    status: string;
}

interface ProjectsIndexProps {
    projects: Paginated<Project>;
    filters: Filters;
    statuses: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/workspace/projects' },
    { title: 'Projects', href: '/workspace/projects' },
];

export default function WorkspaceProjectsIndex({ projects, filters, statuses }: ProjectsIndexProps) {
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const [search, setSearch] = useState(filters.search);
    const [creating, setCreating] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
        starts_on: '',
        due_on: '',
    });

    const applyFilter = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries(merged)) {
            if (v) params[k] = String(v);
        }
        router.get(route('workspace.projects.index'), params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        post(route('workspace.projects.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setCreating(false);
            },
        });
    };

    const { ask, confirmDialog } = useConfirmDialog();

    const archive = (project: Project) => {
        ask({
            title: 'Archive this project?',
            description: `"${project.name}" will be archived and removed from the active project list.`,
            confirmLabel: 'Archive project',
            destructive: true,
            action: (finish) => router.delete(route('workspace.projects.destroy', project.slug), { preserveScroll: true, onFinish: finish }),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspace · Projects" />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">Projects</h1>
                        <p className="text-sm text-muted-foreground">
                            {projects.total} {projects.total === 1 ? 'project' : 'projects'} in this workspace
                        </p>
                    </div>
                    <Button onClick={() => setCreating((s) => !s)}>
                        <Plus className="mr-1" /> {creating ? 'Cancel' : 'New project'}
                    </Button>
                </div>

                {creating && (
                    <form
                        onSubmit={submitCreate}
                        className="space-y-3 rounded-lg border bg-card p-4 shadow-sm sm:p-5"
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground">Name</label>
                                <Input
                                    autoFocus
                                    required
                                    maxLength={120}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && <p className="mt-1 text-xs text-destructive">{errors.name}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <label className="text-xs font-medium text-muted-foreground">Starts on</label>
                                    <Input
                                        type="date"
                                        value={data.starts_on}
                                        onChange={(e) => setData('starts_on', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-muted-foreground">Due on</label>
                                    <Input
                                        type="date"
                                        value={data.due_on}
                                        onChange={(e) => setData('due_on', e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>
                        <div>
                            <label className="text-xs font-medium text-muted-foreground">Description</label>
                            <textarea
                                rows={3}
                                maxLength={2000}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="ghost" onClick={() => setCreating(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Create project
                            </Button>
                        </div>
                    </form>
                )}

                <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <Input
                        type="search"
                        placeholder="Search by name or slug…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full sm:w-64"
                    />
                    <select
                        value={filters.status}
                        onChange={(e) => applyFilter({ status: e.target.value })}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">All statuses</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>{s.label}</option>
                        ))}
                    </select>
                    <Button type="submit" variant="secondary">Filter</Button>
                </form>

                {projects.data.length === 0 ? (
                    <EmptyState onCreate={() => setCreating(true)} />
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {projects.data.map((p) => (
                            <article
                                key={p.id}
                                className="group relative rounded-xl border bg-card p-5 shadow-sm transition-shadow hover:shadow-md"
                            >
                                <header className="mb-3 flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h3 className="truncate font-display text-base font-semibold tracking-tight">
                                            {p.name}
                                        </h3>
                                        <p className="truncate font-mono text-[11px] text-muted-foreground">{p.slug}</p>
                                    </div>
                                    <StatusBadge status={p.status} />
                                </header>
                                {p.description && (
                                    <p className="mb-4 line-clamp-2 text-sm text-muted-foreground">{p.description}</p>
                                )}
                                <dl className="grid grid-cols-3 gap-2 border-t pt-3 text-xs">
                                    <div>
                                        <dt className="text-muted-foreground">Tasks</dt>
                                        <dd className="mt-0.5 tabular-nums">{p.tasks_count}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Owner</dt>
                                        <dd className="mt-0.5 truncate">{p.owner?.name ?? '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Due</dt>
                                        <dd className="mt-0.5 inline-flex items-center gap-1 tabular-nums">
                                            {p.due_on ? (
                                                <>
                                                    <Calendar className="size-3" />
                                                    {new Date(p.due_on).toLocaleDateString()}
                                                </>
                                            ) : '—'}
                                        </dd>
                                    </div>
                                </dl>
                                <button
                                    type="button"
                                    onClick={() => archive(p)}
                                    className="absolute right-3 top-3 hidden text-muted-foreground hover:text-destructive group-hover:inline-flex"
                                    aria-label="Archive project"
                                >
                                    <Trash2 className="size-4" />
                                </button>
                            </article>
                        ))}
                    </div>
                )}
            </div>

            {confirmDialog}
        </AppLayout>
    );
}

function StatusBadge({ status }: { status: 'active' | 'paused' | 'archived' }) {
    const variant: Record<string, 'default' | 'secondary' | 'outline'> = {
        active: 'default',
        paused: 'secondary',
        archived: 'outline',
    };
    return (
        <Badge variant={variant[status] ?? 'secondary'} className="capitalize">
            {status}
        </Badge>
    );
}

function EmptyState({ onCreate }: { onCreate: () => void }) {
    return (
        <div className="rounded-xl border border-dashed border-border/80 bg-muted/20 px-6 py-20 text-center">
            <div className={cn(
                'mx-auto mb-5 inline-flex size-12 items-center justify-center rounded-full',
                'border border-border/80 bg-background text-muted-foreground',
            )}>
                <FolderKanban className="size-5" />
            </div>
            <h2 className="font-display text-lg font-semibold tracking-tight">No projects yet</h2>
            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                Projects organize the work you deliver to clients. Each one can hold tasks and invoices.
            </p>
            <Button className="mt-6" onClick={onCreate}>
                <Plus className="mr-1" /> Create your first project
            </Button>
        </div>
    );
}
