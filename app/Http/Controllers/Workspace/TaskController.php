<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_on' => ['nullable', 'date'],
        ]);

        // Defense in depth: confirm the project belongs to the current tenant.
        // The global scope already filters but the exists: rule above doesn't —
        // an attacker could pass another tenant's project id.
        Project::query()->findOrFail($data['project_id']);

        Task::create($data + ['status' => Task::STATUS_TODO]);

        return redirect()
            ->route('workspace.tasks.index')
            ->with('success', 'Task created.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'in:todo,in_progress,review,done'],
            'priority' => ['sometimes', 'in:low,normal,high,urgent'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
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

        $task->update($data);

        return redirect()
            ->route('workspace.tasks.index')
            ->with('success', 'Task updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()
            ->route('workspace.tasks.index')
            ->with('success', 'Task removed.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => Task::STATUS_TODO, 'label' => 'To do'],
            ['value' => Task::STATUS_IN_PROGRESS, 'label' => 'In progress'],
            ['value' => Task::STATUS_REVIEW, 'label' => 'Review'],
            ['value' => Task::STATUS_DONE, 'label' => 'Done'],
        ];
    }
}
