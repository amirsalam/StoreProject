<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'project' => (string) $request->string('project'),
            'status' => (string) $request->string('status'),
            'assignee' => (string) $request->string('assignee'),
        ];

        $query = Task::query()->with(['project:id,name,slug', 'assignee:id,name']);

        if ($filters['project'] !== '') {
            $query->whereHas('project', fn ($q) => $q->where('slug', $filters['project']));
        }
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if ($filters['assignee'] !== '') {
            $query->where('assignee_id', $filters['assignee']);
        }

        return Inertia::render('workspace/tasks/index', [
            'tasks' => $query->orderBy('position')->latest('id')->paginate(30)->withQueryString(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => $filters,
            'statuses' => $this->statuses(),
            'priorities' => $this->priorities(),
            // People a task can be assigned to: this workspace's team.
            'members' => $this->members()->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'in:todo,in_progress,review,done'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'assignee_id' => ['nullable', 'integer', $this->memberRule()],
            'due_on' => ['nullable', 'date'],
        ]);

        // Defense in depth: confirm the project belongs to the current tenant.
        // The global scope already filters but the exists: rule above doesn't —
        // an attacker could pass another tenant's project id.
        Project::query()->findOrFail($data['project_id']);

        $data['status'] ??= Task::STATUS_TODO;
        if ($data['status'] === Task::STATUS_DONE) {
            $data['completed_at'] = now();
        }

        Task::create($data);

        // Back to the same filtered board the task was added from.
        return back()->with('success', __('Task created.'));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['sometimes', 'integer', 'exists:projects,id'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'in:todo,in_progress,review,done'],
            'priority' => ['sometimes', 'in:low,normal,high,urgent'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', $this->memberRule()],
            'due_on' => ['sometimes', 'nullable', 'date'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        // Stamp completed_at when crossing into Done.
        if (($data['status'] ?? null) === Task::STATUS_DONE && $task->status !== Task::STATUS_DONE) {
            $data['completed_at'] = now();
        }
        if (($data['status'] ?? null) !== Task::STATUS_DONE && $task->status === Task::STATUS_DONE) {
            $data['completed_at'] = null;
        }

        if (isset($data['project_id'])) {
            // Same tenant check as store(): another workspace's project 404s.
            Project::query()->findOrFail($data['project_id']);
        }

        $task->update($data);

        return back()->with('success', __('Task updated.'));
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return back()->with('success', __('Task removed.'));
    }

    /**
     * This workspace's members, by name.
     *
     * @return Collection<int, User>
     */
    private function members(): Collection
    {
        $tenant = app(TenantContext::class)->current();

        return $tenant ? $tenant->users()->orderBy('name')->get(['users.id', 'users.name']) : collect();
    }

    /**
     * Assignees must be on this workspace's team — not any user id.
     */
    private function memberRule(): In
    {
        return Rule::in($this->members()->pluck('id')->all());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function priorities(): array
    {
        return [
            ['value' => Task::PRIORITY_LOW, 'label' => __('Low')],
            ['value' => Task::PRIORITY_NORMAL, 'label' => __('Normal')],
            ['value' => Task::PRIORITY_HIGH, 'label' => __('High')],
            ['value' => Task::PRIORITY_URGENT, 'label' => __('Urgent')],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => Task::STATUS_TODO, 'label' => __('To do')],
            ['value' => Task::STATUS_IN_PROGRESS, 'label' => __('In progress')],
            ['value' => Task::STATUS_REVIEW, 'label' => __('Review')],
            ['value' => Task::STATUS_DONE, 'label' => __('Done')],
        ];
    }
}
