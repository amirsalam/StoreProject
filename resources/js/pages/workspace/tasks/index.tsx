import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, ListTodo, Plus } from 'lucide-react';
import { useState } from 'react';
import TaskDialog, { type Option, type Task } from './task-dialog';

interface Filters {
    project: string;
    status: string;
    assignee: string;
}

interface TasksIndexProps {
    tasks: Paginated<Task>;
    projects: { id: number; name: string; slug: string }[];
    filters: Filters;
    statuses: Option[];
    priorities: Option[];
    members: { id: number; name: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/workspace/projects' },
    { title: 'Tasks', href: '/workspace/tasks' },
];

export default function WorkspaceTasksIndex({ tasks, projects, filters, statuses, priorities, members }: TasksIndexProps) {
    const { __ } = useTranslate();
    // null = closed, 'new' = create, a Task = edit.
    const [editing, setEditing] = useState<Task | 'new' | null>(null);
    const filteredProjectId = projects.find((p) => p.slug === filters.project)?.id ?? null;
    const hasFilters = Boolean(filters.project || filters.status || filters.assignee);
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;

    const applyFilter = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries(merged)) {
            if (v) params[k] = String(v);
        }
        router.get(route('workspace.tasks.index'), params, { preserveScroll: true, preserveState: true, replace: true });
    };

    const toggleDone = (task: Task) => {
        const nextStatus = task.status === 'done' ? 'todo' : 'done';
        router.patch(route('workspace.tasks.update', task.id), { status: nextStatus }, { preserveScroll: true });
    };

    const grouped: Record<string, Task[]> = {
        todo: [],
        in_progress: [],
        review: [],
        done: [],
    };
    for (const t of tasks.data) {
        grouped[t.status]?.push(t);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Workspace · Tasks')} />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">{__('Tasks')}</h1>
                        <p className="text-muted-foreground text-sm">
                            {tasks.total === 1 ? __('1 task') : __(':count tasks', { count: tasks.total })} ·{' '}
                            {projects.length === 1 ? __('1 project') : __(':count projects', { count: projects.length })}
                        </p>
                    </div>
                    {projects.length > 0 && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> {__('New task')}
                        </Button>
                    )}
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <select
                        value={filters.project}
                        onChange={(e) => applyFilter({ project: e.target.value })}
                        className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">{__('All projects')}</option>
                        {projects.map((p) => (
                            <option key={p.id} value={p.slug}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                    <select
                        value={filters.status}
                        onChange={(e) => applyFilter({ status: e.target.value })}
                        className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">{__('All statuses')}</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                </div>

                {tasks.data.length === 0 ? (
                    <EmptyState
                        hasProjects={projects.length > 0}
                        hasFilters={hasFilters}
                        onCreate={() => setEditing('new')}
                        onClearFilters={() => applyFilter({ project: '', status: '', assignee: '' })}
                    />
                ) : (
                    <div className="grid gap-4 lg:grid-cols-4">
                        {(['todo', 'in_progress', 'review', 'done'] as const).map((col) => (
                            <section key={col} className="bg-card rounded-lg border">
                                <header className="flex items-center justify-between border-b px-4 py-2.5">
                                    <h3 className="text-muted-foreground font-mono text-[11px] tracking-wider uppercase">
                                        {statuses.find((s) => s.value === col)?.label}
                                    </h3>
                                    <Badge variant="secondary" className="font-mono text-[10px] tabular-nums">
                                        {grouped[col].length}
                                    </Badge>
                                </header>
                                <ul className="divide-y">
                                    {grouped[col].length === 0 ? (
                                        <li className="text-muted-foreground px-4 py-6 text-center text-xs">{__('No tasks')}</li>
                                    ) : (
                                        grouped[col].map((task) => (
                                            <li key={task.id} className="group hover:bg-muted/30 flex items-start gap-3 px-4 py-3">
                                                <button
                                                    type="button"
                                                    onClick={() => toggleDone(task)}
                                                    aria-label={task.status === 'done' ? __('Reopen task') : __('Mark task done')}
                                                    className={cn(
                                                        'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border transition-colors',
                                                        task.status === 'done'
                                                            ? 'border-emerald-500 bg-emerald-500 text-white'
                                                            : 'border-muted-foreground/30 hover:border-foreground',
                                                    )}
                                                >
                                                    {task.status === 'done' && <CheckCircle2 className="size-3" />}
                                                </button>
                                                <div className="min-w-0 flex-1 space-y-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => setEditing(task)}
                                                        className={cn(
                                                            'block text-start text-sm hover:underline',
                                                            task.status === 'done' && 'text-muted-foreground line-through',
                                                        )}
                                                    >
                                                        {task.title}
                                                    </button>
                                                    <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-[11px]">
                                                        {task.project && <span className="truncate">{task.project.name}</span>}
                                                        {task.assignee && <span>· {task.assignee.name}</span>}
                                                        {task.due_on && (
                                                            <span>· {__('due :date', { date: new Date(task.due_on).toLocaleDateString() })}</span>
                                                        )}
                                                        {task.priority !== 'normal' && (
                                                            <Badge variant="outline" className="capitalize">
                                                                {__(task.priority)}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </li>
                                        ))
                                    )}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </div>

            <TaskDialog
                open={editing !== null}
                onClose={() => setEditing(null)}
                task={editing === 'new' ? null : editing}
                projects={projects}
                members={members}
                statuses={statuses}
                priorities={priorities}
                defaultProjectId={filteredProjectId}
            />
        </AppLayout>
    );
}

function EmptyState({
    hasProjects,
    hasFilters,
    onCreate,
    onClearFilters,
}: {
    hasProjects: boolean;
    hasFilters: boolean;
    onCreate: () => void;
    onClearFilters: () => void;
}) {
    const { __ } = useTranslate();
    return (
        <div className="border-border/80 bg-muted/20 rounded-xl border border-dashed px-6 py-20 text-center">
            <div className="border-border/80 bg-background text-muted-foreground mx-auto mb-5 inline-flex size-12 items-center justify-center rounded-full border">
                <ListTodo className="size-5" />
            </div>
            {!hasProjects ? (
                <>
                    <h2 className="font-display text-lg font-semibold tracking-tight">{__('No tasks yet')}</h2>
                    <p className="text-muted-foreground mx-auto mt-1 max-w-sm text-sm">
                        {__('Tasks live inside a project. Create a project first, then come back here to add tasks.')}
                    </p>
                    <Button asChild className="mt-6">
                        <a href={route('workspace.projects.index')}>{__('Go to projects')}</a>
                    </Button>
                </>
            ) : (
                <>
                    <h2 className="font-display text-lg font-semibold tracking-tight">
                        {hasFilters ? __('No tasks match these filters') : __('No tasks yet')}
                    </h2>
                    <p className="text-muted-foreground mx-auto mt-1 max-w-sm text-sm">
                        {hasFilters ? __('Add one here, or clear the filters to see every task.') : __('Add the first task to one of your projects.')}
                    </p>
                    <div className="mt-6 flex flex-wrap justify-center gap-2">
                        <Button onClick={onCreate}>
                            <Plus /> {__('New task')}
                        </Button>
                        {hasFilters && (
                            <Button variant="outline" onClick={onClearFilters}>
                                {__('Clear filters')}
                            </Button>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}
