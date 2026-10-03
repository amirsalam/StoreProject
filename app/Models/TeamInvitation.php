<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pending team invitations.
 *
 * NOT BelongsToTenant: tenant_id is on the row, but central-domain code
 * (the accept page) needs to look invitations up by token without a
 * tenant context — the resolver fills it in from the row instead.
 * Use the tenant() relation if you need scoped queries.
 */
class TeamInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'invited_by_id',
        'email',
        'role',
        'token',
        'accepted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isOpen(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }
}
