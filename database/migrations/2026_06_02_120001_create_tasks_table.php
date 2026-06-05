<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('todo');          // todo|in_progress|review|done
            $table->string('priority', 8)->default('normal');       // low|normal|high|urgent
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('position')->default(0);         // for kanban board ordering
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'project_id', 'status']);
            $table->index(['tenant_id', 'assignee_id']);
            $table->index(['tenant_id', 'due_on']);                 // upcoming-tasks queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
