import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, ListTodo } from 'lucide-react';

interface Task {
    id: number;
    title: string;
    description: string | null;
    status: 'todo' | 'in_progress' | 'review' | 'done';
    priority: 'low' | 'normal' | 'high' | 'urgent';
    due_on: string | null;
    position: number;
    project?: { id: number; name: string; slug: string } | null;
    assignee?: { id: number; name: string } | null;
}

interface Option {
    value: string;
    label: string;
}

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
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/workspace/projects' },
    { title: 'Tasks', href: '/workspace/tasks' },
];

export default function WorkspaceTasksIndex({ tasks, projects, filters, statuses }: TasksIndexProps) {
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
            <Head title="Workspace · Tasks" />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">Tasks</h1>
                        <p className="text-sm text-muted-foreground">
                            {tasks.total} {tasks.total === 1 ? 'task' : 'tasks'} across {projects.length} {projects.length === 1 ? 'project' : 'projects'}
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <select
                        value={filters.project}
                        onChange={(e) => applyFilter({ project: e.target.value })}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">All projects</option>
                        {projects.map((p) => (
                            <option key={p.id} value={p.slug}>{p.name}</option>
                        ))}
                    </select>
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
                </div>

                {tasks.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="grid gap-4 lg:grid-cols-4">
                        {(['todo', 'in_progress', 'review', 'done'] as const).map((col) => (
                            <section key={col} className="rounded-lg border bg-card">
                                <header className="flex items-center justify-between border-b px-4 py-2.5">
                                    <h3 className="font-mono text-[11px] uppercase tracking-wider text-muted-foreground">
                                        {statuses.find((s) => s.value === col)?.label}
                                    </h3>
                                    <Badge variant="secondary" className="font-mono text-[10px] tabular-nums">
                                        {grouped[col].length}
                                    </Badge>
                                </header>
                                <ul className="divide-y">
                                    {grouped[col].length === 0 ? (
                                        <li className="px-4 py-6 text-center text-xs text-muted-foreground">No tasks</li>
                                    ) : grouped[col].map((task) => (
                                        <li key={task.id} className="group flex items-start gap-3 px-4 py-3 hover:bg-muted/30">
                                            <button
                                                type="button"
                                                onClick={() => toggleDone(task)}
                                                aria-label={task.status === 'done' ? 'Reopen task' : 'Mark task done'}
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
                                                <p className={cn(
                                                    'text-sm',
                                                    task.status === 'done' && 'text-muted-foreground line-through',
                                                )}>
                                                    {task.title}
                                                </p>
                                                <div className="flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
                                                    {task.project && <span className="truncate">{task.project.name}</span>}
                                                    {task.assignee && <span>· {task.assignee.name}</span>}
                                                    {task.due_on && <span>· due {new Date(task.due_on).toLocaleDateString()}</span>}
                                                    {task.priority !== 'normal' && (
                                                        <Badge variant="outline" className="capitalize">{task.priority}</Badge>
                                                    )}
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function EmptyState() {
    return (
        <div className="rounded-xl border border-dashed border-border/80 bg-muted/20 px-6 py-20 text-center">
            <div className="mx-auto mb-5 inline-flex size-12 items-center justify-center rounded-full border border-border/80 bg-background text-muted-foreground">
                <ListTodo className="size-5" />
            </div>
            <h2 className="font-display text-lg font-semibold tracking-tight">No tasks yet</h2>
            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                Tasks live inside a project. Create a project first, then add tasks from its detail page.
            </p>
            <Button asChild className="mt-6">
                <a href={route('workspace.projects.index')}>Go to projects</a>
            </Button>
        </div>
    );
}
