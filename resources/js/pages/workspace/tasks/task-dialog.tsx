import { useConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/hooks/use-translate';
import { router, useForm } from '@inertiajs/react';
import { Loader2, Trash2 } from 'lucide-react';
import { FormEvent, useEffect } from 'react';

export interface Task {
    id: number;
    title: string;
    description: string | null;
    status: 'todo' | 'in_progress' | 'review' | 'done';
    priority: 'low' | 'normal' | 'high' | 'urgent';
    due_on: string | null;
    position: number;
    project_id: number;
    assignee_id: number | null;
    project?: { id: number; name: string; slug: string } | null;
    assignee?: { id: number; name: string } | null;
}

export interface Option {
    value: string;
    label: string;
}

interface Props {
    open: boolean;
    onClose: () => void;
    /** The task being edited; null to create one. */
    task: Task | null;
    projects: { id: number; name: string; slug: string }[];
    members: { id: number; name: string }[];
    statuses: Option[];
    priorities: Option[];
    /** Pre-selected project for a new task (e.g. the one the board is filtered on). */
    defaultProjectId: number | null;
}

const selectClass = 'border-input bg-background w-full rounded-md border px-3 py-2 text-sm';

/**
 * Create / edit a task. Any of the workspace's projects can be chosen.
 */
export default function TaskDialog({ open, onClose, task, projects, members, statuses, priorities, defaultProjectId }: Props) {
    const { __ } = useTranslate();
    const { ask, confirmDialog } = useConfirmDialog();

    const blank = () => ({
        project_id: String(defaultProjectId ?? projects[0]?.id ?? ''),
        title: '',
        description: '',
        status: 'todo',
        priority: 'normal',
        assignee_id: '',
        due_on: '',
    });

    const { data, setData, post, patch, processing, errors, clearErrors, transform } = useForm(blank());

    // Load the task (or a blank form) each time the dialog opens.
    useEffect(() => {
        if (!open) return;
        clearErrors();
        setData(
            task
                ? {
                      project_id: String(task.project_id),
                      title: task.title,
                      description: task.description ?? '',
                      status: task.status,
                      priority: task.priority,
                      assignee_id: task.assignee_id ? String(task.assignee_id) : '',
                      due_on: task.due_on ? task.due_on.slice(0, 10) : '',
                  }
                : blank(),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, task?.id]);

    transform((values) => ({
        ...values,
        project_id: Number(values.project_id),
        assignee_id: values.assignee_id ? Number(values.assignee_id) : null,
        due_on: values.due_on || null,
        description: values.description || null,
    }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };
        if (task) {
            patch(route('workspace.tasks.update', task.id), options);
        } else {
            post(route('workspace.tasks.store'), options);
        }
    };

    const remove = () => {
        if (!task) return;
        ask({
            title: __('Delete this task?'),
            description: __('":title" will be removed.', { title: task.title }),
            confirmLabel: __('Delete'),
            destructive: true,
            action: (finish) =>
                router.delete(route('workspace.tasks.destroy', task.id), {
                    preserveScroll: true,
                    onFinish: finish,
                    onSuccess: () => onClose(),
                }),
        });
    };

    return (
        <>
            <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{task ? __('Edit task') : __('New task')}</DialogTitle>
                        <DialogDescription>{__('Tasks belong to a project. Pick any of your projects.')}</DialogDescription>
                    </DialogHeader>

                    <form id="task-form" onSubmit={submit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="task-project">{__('Project')}</Label>
                            <select
                                id="task-project"
                                value={data.project_id}
                                onChange={(e) => setData('project_id', e.target.value)}
                                className={selectClass}
                                required
                            >
                                {projects.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                            {errors.project_id && <p className="text-destructive text-xs">{errors.project_id}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="task-title">{__('Title')}</Label>
                            <Input
                                id="task-title"
                                autoFocus
                                required
                                maxLength={200}
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                            />
                            {errors.title && <p className="text-destructive text-xs">{errors.title}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="task-description">{__('Description')}</Label>
                            <textarea
                                id="task-description"
                                rows={3}
                                maxLength={5000}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-hidden"
                            />
                            {errors.description && <p className="text-destructive text-xs">{errors.description}</p>}
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="task-status">{__('Status')}</Label>
                                <select
                                    id="task-status"
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className={selectClass}
                                >
                                    {statuses.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="task-priority">{__('Priority')}</Label>
                                <select
                                    id="task-priority"
                                    value={data.priority}
                                    onChange={(e) => setData('priority', e.target.value)}
                                    className={selectClass}
                                >
                                    {priorities.map((p) => (
                                        <option key={p.value} value={p.value}>
                                            {p.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="task-assignee">{__('Assignee')}</Label>
                                <select
                                    id="task-assignee"
                                    value={data.assignee_id}
                                    onChange={(e) => setData('assignee_id', e.target.value)}
                                    className={selectClass}
                                >
                                    <option value="">{__('Unassigned')}</option>
                                    {members.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.assignee_id && <p className="text-destructive text-xs">{errors.assignee_id}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="task-due">{__('Due date')}</Label>
                                <Input id="task-due" type="date" value={data.due_on} onChange={(e) => setData('due_on', e.target.value)} />
                                {errors.due_on && <p className="text-destructive text-xs">{errors.due_on}</p>}
                            </div>
                        </div>
                    </form>

                    <DialogFooter className="gap-2 sm:justify-between">
                        {task ? (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={remove}
                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            >
                                <Trash2 /> {__('Delete')}
                            </Button>
                        ) : (
                            <span />
                        )}
                        <div className="flex gap-2">
                            <Button type="button" variant="ghost" onClick={onClose}>
                                {__('Cancel')}
                            </Button>
                            <Button type="submit" form="task-form" disabled={processing}>
                                {processing && <Loader2 className="animate-spin" />}
                                {task ? __('Save changes') : __('Create task')}
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            {confirmDialog}
        </>
    );
}
