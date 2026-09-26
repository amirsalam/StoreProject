<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Plans\PlanGate;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'status' => (string) $request->string('status'),
        ];

        $query = Project::query()->with('owner:id,name');

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('slug', 'like', $term));
        }
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('workspace/projects/index', [
            'projects' => $query->withCount('tasks')->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(Request $request, PlanGate $gate): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $tenant = app(TenantContext::class)->current();
        if ($tenant && ! $gate->withinLimit($tenant, 'projects', 1)) {
            abort(402, 'You\'ve reached your plan\'s project limit. Upgrade to add more.');
        }

        $project = Project::create([
            'owner_id' => $request->user()->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'status' => Project::STATUS_ACTIVE,
            'starts_on' => $data['starts_on'] ?? null,
            'due_on' => $data['due_on'] ?? null,
        ]);

        return redirect()
            ->route('workspace.projects.index')
            ->with('success', "Project \"{$project->name}\" created.");
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,paused,archived'],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $project->update($data);

        return redirect()
            ->route('workspace.projects.index')
            ->with('success', "Project \"{$project->name}\" updated.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()
            ->route('workspace.projects.index')
            ->with('success', 'Project archived.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => Project::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => Project::STATUS_PAUSED, 'label' => 'Paused'],
            ['value' => Project::STATUS_ARCHIVED, 'label' => 'Archived'],
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        // Global scope keeps this tenant-scoped already; no need for forTenant().
        while (Project::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
