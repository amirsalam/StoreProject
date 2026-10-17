<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A unit of work a tenant is delivering — e.g. a website build, a
 * design sprint, an integration. Holds tasks and (optionally)
 * invoices.
 */
class Project extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'tenant_id',
        'owner_id',
        'name',
        'slug',
        'description',
        'status',
        'starts_on',
        'due_on',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'due_on' => 'date',
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        // Stable URLs scoped to the slug; global scope keeps it tenant-safe.
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
