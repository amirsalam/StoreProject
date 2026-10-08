<?php

namespace Tests\Feature\Workspace;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Workspace → Tasks: create a task in any of the workspace's projects,
 * edit it, and delete it — all from the tasks board.
 */
class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenancy.central_domain' => 'example.test',
            'tenancy.central_fallback_tenant' => null,
        ]);
        $this->seed(PlansSeeder::class);

        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->tenant = Tenant::factory()->forOwner($this->owner)->create();
        $this->tenant->users()->attach($this->owner->id, ['role' => Tenant::ROLE_OWNER, 'joined_at' => now()]);
        app(TenantContext::class)->set($this->tenant);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->set(null);
        parent::tearDown();
    }

    private function url(string $path): string
    {
        return "http://{$this->tenant->slug}.example.test{$path}";
    }

    private function project(string $name): Project
    {
        return Project::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name]);
    }

    public function test_the_board_lists_projects_team_members_and_priorities(): void
    {
        $this->project('Website Redesign');
        $this->project('Mobile App');

        $this->actingAs($this->owner)->get($this->url('/workspace/tasks'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspace/tasks/index')
                ->has('projects', 2)
                ->where('members', [['id' => $this->owner->id, 'name' => 'Owner']])
                ->has('priorities', 4));
    }

    public function test_a_task_can_be_created_in_any_project_and_returns_to_the_filtered_board(): void
    {
        $this->project('Website Redesign');
        $mobile = $this->project('Mobile App');
        $board = $this->url('/workspace/tasks?project='.$mobile->slug.'&status=todo');

        $this->actingAs($this->owner)
            ->from($board)
            ->post($this->url('/workspace/tasks'), [
                'project_id' => $mobile->id,
                'title' => 'Ship the login screen',
                'description' => 'Email + social',
                'priority' => 'high',
                'assignee_id' => $this->owner->id,
                'due_on' => '2026-11-01',
            ])
            ->assertRedirect($board)
            ->assertSessionHas('success');

        $task = Task::query()->firstOrFail();
        $this->assertSame($mobile->id, $task->project_id);
        $this->assertSame('todo', $task->status);
        $this->assertSame('high', $task->priority);
        $this->assertSame($this->owner->id, $task->assignee_id);
    }

    public function test_a_task_created_as_done_is_stamped_completed(): void
    {
        $project = $this->project('Website Redesign');

        $this->actingAs($this->owner)->post($this->url('/workspace/tasks'), [
            'project_id' => $project->id,
            'title' => 'Already finished',
            'priority' => 'normal',
            'status' => 'done',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(Task::query()->firstOrFail()->completed_at);
    }

    public function test_tasks_can_only_be_assigned_to_this_workspaces_team(): void
    {
        $project = $this->project('Website Redesign');
        $outsider = User::factory()->create();

        $this->actingAs($this->owner)->post($this->url('/workspace/tasks'), [
            'project_id' => $project->id,
            'title' => 'Sneaky',
            'priority' => 'normal',
            'assignee_id' => $outsider->id,
        ])->assertSessionHasErrors('assignee_id');

        $this->assertSame(0, Task::query()->count());
    }

    public function test_a_task_can_be_edited_and_moved_to_another_project(): void
    {
        $web = $this->project('Website Redesign');
        $mobile = $this->project('Mobile App');
        $task = Task::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $web->id, 'status' => 'todo']);

        $this->actingAs($this->owner)->patch($this->url("/workspace/tasks/{$task->id}"), [
            'project_id' => $mobile->id,
            'title' => 'Renamed',
            'status' => 'in_progress',
            'priority' => 'urgent',
            'assignee_id' => null,
        ])->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame($mobile->id, $task->project_id);
        $this->assertSame('Renamed', $task->title);
        $this->assertSame('in_progress', $task->status);
        $this->assertSame('urgent', $task->priority);
    }

    public function test_a_task_can_be_deleted(): void
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project('Website Redesign')->id,
        ]);

        $this->actingAs($this->owner)->delete($this->url("/workspace/tasks/{$task->id}"))->assertSessionHas('success');

        $this->assertSoftDeleted($task);
    }
}
