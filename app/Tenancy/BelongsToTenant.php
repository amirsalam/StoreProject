<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;

/**
 * Tenancy primitive for every multi-tenant model.
 *
 * Bolts on three guarantees:
 *
 *   1. Global scope — every query is automatically constrained to the
 *      current tenant. No tenant in context = empty result set
 *      (defense in depth — we refuse to "fall back" to global data).
 *
 *   2. Auto-fill — on INSERT, tenant_id is populated from the active
 *      TenantContext if the caller didn't provide one.
 *
 *   3. Tenant relation — convenience `$model->tenant` BelongsTo.
 *
 * Opting out (e.g. for cross-tenant admin tooling or background jobs)
 * is an explicit decision:
 *
 *     Product::query()->withoutGlobalScope('tenant')->get();
 *     Product::query()->forTenant($tenant)->get();
 *
 * Both forms are grep-able in code review, which is the whole point.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = app(TenantContext::class)->id();

            // A tenant in context always scopes the query — in web requests
            // AND in console contexts. Tenant-aware jobs set context
            // explicitly before running their work, and must get the same
            // isolation a web request does.
            if ($tenantId !== null) {
                $table = $query->getModel()->getTable();
                $query->where("{$table}.tenant_id", $tenantId);

                return;
            }

            // No tenant in context. Console contexts (artisan, queue workers
            // without tenant runner, tinker, factories during testing) are
            // exempt — they typically operate cross-tenant and the global
            // scope would silently empty every query.
            if (App::runningInConsole()) {
                return;
            }

            // No tenant in context AND we're in a web/api request — refuse
            // to return any data. A leak here would silently expose every
            // tenant's rows to a request that escaped ResolveTenant.
            $query->whereRaw('1 = 0');
        });

        static::creating(function (Model $model) {
            if ($model->tenant_id !== null) {
                return;
            }
            $tenantId = app(TenantContext::class)->id();
            if ($tenantId !== null) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Convenience scope for cross-tenant code (admin tools, jobs) that
     * explicitly wants to target one tenant without flipping global
     * TenantContext.
     *
     *     Product::query()->forTenant($tenant)->get();
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->withoutGlobalScope('tenant')
            ->where($query->getModel()->getTable().'.tenant_id', $id);
    }

    /**
     * Mark `tenant_id` as fillable on the model. Models can override or
     * extend $fillable; this trait just declares the column so factories
     * + mass-assignment work without per-model boilerplate.
     */
    public function initializeBelongsToTenant(): void
    {
        if (! in_array('tenant_id', $this->fillable, true)) {
            $this->fillable[] = 'tenant_id';
        }
    }
}
