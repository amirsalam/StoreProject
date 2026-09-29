<?php

namespace App\Domain\Dashboard;

use App\Models\DailyMetric;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Role-aware dashboard payload builder.
 *
 *   $payload = app(DashboardService::class)->forUser($request->user());
 *   return Inertia::render("dashboard/{$payload['layout']}", $payload);
 *
 * Resolves the active role, picks the right widget set, and returns
 * a payload shaped to the WidgetPayload protocol documented in
 * docs/dashboard-architecture.md §3.
 *
 * Reads come from daily_metrics where possible (pre-aggregated by
 * MetricsAggregator). Hits Redis with a 5-minute TTL so the
 * dashboard endpoint stays under 200ms even on a cold DB.
 */
class DashboardService
{
    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{layout: string, user: array, widgets: array<int, array>}
     */
    public function forUser(User $user): array
    {
        $layout = $this->resolveRole($user);
        $tenant = $this->tenantContext->current();
        $cacheKey = $this->cacheKey($layout, $tenant?->id, $user->id);

        $widgets = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, fn () => match ($layout) {
            'super-admin' => $this->superAdminWidgets(),
            'vendor' => $this->vendorWidgets($tenant, $user),
            'team' => $this->teamWidgets($tenant, $user),
            default => $this->customerWidgets($user),
        });

        return [
            'layout' => $layout,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $layout,
            ],
            'widgets' => $widgets,
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Bust the cache for a user (or all users in a tenant).
     */
    public function invalidate(?Tenant $tenant = null, ?User $user = null): void
    {
        if ($user) {
            $layout = $this->resolveRole($user);
            Cache::forget($this->cacheKey($layout, $tenant?->id, $user->id));

            return;
        }
        // Coarse-grained bust: bump the version key so every dashboard payload
        // misses the cache on next read. Useful after a major data event.
        // (Not Cache::increment(): on a missing key the database store does
        // nothing and the array store writes 1 — the default — so the bust
        // silently never happened.)
        Cache::forever('cache:dashboard:version', (int) Cache::get('cache:dashboard:version', 1) + 1);
    }

    /**
     * Map a Laravel User to one of the four dashboard layouts.
     */
    private function resolveRole(User $user): string
    {
        if ($user->hasAnyRole(['super-admin', 'platform-admin']) || $user->is_admin) {
            return 'super-admin';
        }
        if ($user->hasRole('vendor')) {
            return 'vendor';
        }
        if ($user->hasRole('team-member')) {
            return 'team';
        }

        return 'customer';
    }

    /* ──────────────────────────────────────────────────────────────── */
    /*  SUPER-ADMIN */
    /* ──────────────────────────────────────────────────────────────── */

    private function superAdminWidgets(): array
    {
        $today = CarbonImmutable::today();
        $thirtyDays = $today->subDays(30)->toDateString();

        // Platform-wide totals (tenant_id IS NULL) summed across the last 30 days.
        // The per-store rows hold the same money split by store; adding them
        // too counted every order twice.
        $revenueCents = (int) DB::table('daily_metrics')
            ->whereNull('tenant_id')
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            ->where('recorded_on', '>=', $thirtyDays)
            ->sum('value');

        $ordersCount = (int) DB::table('daily_metrics')
            ->whereNull('tenant_id')
            ->where('metric_key', DailyMetric::KEY_ORDERS_COUNT)
            ->where('recorded_on', '>=', $thirtyDays)
            ->sum('value');

        $activeVendors = Tenant::query()->whereNotNull('owner_id')->count();
        $totalCustomers = User::query()->count();

        // Pending vendor approvals — assumes a `tenants.approved_at` column.
        // Falls back to 0 if the column doesn't exist yet so the dashboard
        // doesn't 500 in a fresh install.
        $pendingApprovals = $this->safeCount(
            fn () => Tenant::query()->whereNull('approved_at')->count(),
        );

        return [
            $this->statWidget('total_revenue_30d', __('Revenue (30 days)'), $revenueCents, 'money', 'USD'),
            $this->statWidget('orders_30d', __('Orders (30 days)'), $ordersCount, 'integer'),
            // admin.vendors.index ships with vendor approval (feat/vendor-approval);
            // until then these links are simply not shown.
            $this->statWidget('active_vendors', __('Active vendors'), $activeVendors, 'integer', cta: $this->cta('admin.vendors.index', __('Manage'))),
            $this->statWidget('total_customers', __('Total customers'), $totalCustomers, 'integer'),
            $this->statWidget('pending_approvals', __('Pending approvals'), $pendingApprovals, 'integer', cta: $this->cta('admin.vendors.index', __('Review'), ['status' => 'pending'])),
            $this->revenueTrendChart($thirtyDays, $today->toDateString(), cta: $this->cta('admin.orders.index', __('View orders'), ['status' => 'paid'])),
            $this->recentOrdersTable(cta: $this->cta('admin.orders.index', __('View all'))),
            $this->quickActions([
                ['label' => __('Add product'), 'href' => $this->link('admin.products.create'), 'icon' => 'plus', 'primary' => true],
                ['label' => __('View orders'), 'href' => $this->link('admin.orders.index'), 'icon' => 'receipt'],
                ['label' => __('Payment gateways'), 'href' => $this->link('admin.payment-gateways.index'), 'icon' => 'wallet'],
                ['label' => __('Email settings'), 'href' => $this->link('admin.mail.edit'), 'icon' => 'mail'],
            ]),
        ];
    }

    /* ──────────────────────────────────────────────────────────────── */
    /*  VENDOR */
    /* ──────────────────────────────────────────────────────────────── */

    private function vendorWidgets(?Tenant $tenant, User $user): array
    {
        if (! $tenant) {
            return $this->customerWidgets($user); // no active tenant context → fall back
        }

        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth()->toDateString();
        $thirtyDays = $today->subDays(30)->toDateString();

        $monthRevenueCents = (int) DailyMetric::query()
            ->where('tenant_id', $tenant->id)
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            ->where('recorded_on', '>=', $monthStart)
            ->sum('value');

        $totalSales = (int) DailyMetric::query()
            ->where('tenant_id', $tenant->id)
            ->where('metric_key', DailyMetric::KEY_ORDERS_COUNT)
            ->sum('value');

        $pendingPayoutCents = $this->safeSum(
            fn () => DB::table('payouts')
                ->where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->sum('amount_cents'),
        );

        return [
            $this->statWidget('revenue_month', __('Revenue this month'), $monthRevenueCents, 'money', $tenant->settings['currency'] ?? 'USD'),
            $this->statWidget('total_sales', __('Total sales'), $totalSales, 'integer'),
            $this->statWidget('pending_payout', __('Pending payout'), $pendingPayoutCents, 'money', $tenant->settings['currency'] ?? 'USD', cta: $this->cta('payouts.index', __('Request'))),
            $this->statWidget('conversion_rate', __('Conversion rate'), 0, 'percent'),
            $this->revenueTrendChart($thirtyDays, $today->toDateString(), $tenant->id),
            $this->recentOrdersTable($tenant->id),
            $this->quickActions([
                ['label' => __('Add product'), 'href' => $this->link('admin.products.create'), 'icon' => 'plus', 'primary' => true],
                ['label' => __('My store'), 'href' => $this->link('workspace.vendor.edit'), 'icon' => 'store'],
                ['label' => __('Payment gateway'), 'href' => $this->link('workspace.billing.index'), 'icon' => 'wallet'],
            ]),
        ];
    }

    /* ──────────────────────────────────────────────────────────────── */
    /*  CUSTOMER */
    /* ──────────────────────────────────────────────────────────────── */

    private function customerWidgets(User $user): array
    {
        $totalSpentCents = $this->safeSum(
            fn () => DB::table('orders')
                ->where('user_id', $user->id)
                ->where('status', 'paid')
                ->sum(DB::raw('total * 100')),
        );

        $activeSubs = $this->safeCount(
            fn () => DB::table('subscriptions')->where('user_id', $user->id)->where('status', 'active')->count(),
        );

        $walletCents = (int) DB::table('wallets')
            ->where('user_id', $user->id)
            ->where('currency', 'USD')
            ->value('balance_cents') ?? 0;

        return [
            $this->statWidget('total_spent', __('Total spent'), (int) $totalSpentCents, 'money', 'USD'),
            $this->statWidget('active_subs', __('Active subscriptions'), $activeSubs, 'integer'),
            $this->statWidget('wallet_balance', __('Wallet balance'), $walletCents, 'money', 'USD', cta: $this->cta('wallet.index', __('View'))),
            $this->recentOrdersTable(null, $user->id, title: __('Your recent orders')),
            $this->quickActions([
                ['label' => __('Browse products'), 'href' => $this->link('products.index'), 'icon' => 'shopping-bag', 'primary' => true],
                ['label' => __('Downloads'), 'href' => $this->link('downloads.index'), 'icon' => 'download'],
                ['label' => __('Contact support'), 'href' => $this->link('contact'), 'icon' => 'help'],
            ]),
        ];
    }

    /* ──────────────────────────────────────────────────────────────── */
    /*  TEAM-MEMBER */
    /* ──────────────────────────────────────────────────────────────── */

    private function teamWidgets(?Tenant $tenant, User $user): array
    {
        $openTasks = $this->safeCount(
            fn () => DB::table('tasks')
                ->where('assignee_id', $user->id)
                ->whereIn('status', ['todo', 'in_progress'])
                ->count(),
        );

        $unreadNotifications = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return [
            $this->statWidget('open_tasks', __('Open tasks'), $openTasks, 'integer', cta: $this->cta('workspace.tasks.index', __('View'))),
            $this->statWidget('unread_alerts', __('Unread notifications'), $unreadNotifications, 'integer'),
            $this->quickActions([
                ['label' => __('Open my tasks'), 'href' => $this->link('workspace.tasks.index'), 'icon' => 'check-square', 'primary' => true],
                ['label' => __('Activity log'), 'href' => $this->link('activity.index'), 'icon' => 'activity'],
            ]),
        ];
    }

    /* ──────────────────────────────────────────────────────────────── */
    /*  WIDGET BUILDERS */
    /* ──────────────────────────────────────────────────────────────── */

    /**
     * Build a stat widget. `value` is raw (cents for money, count for integer,
     * 0–100 for percent). Frontend formats per `format`.
     */
    private function statWidget(
        string $key,
        string $title,
        int $value,
        string $format,
        ?string $currency = null,
        ?array $cta = null,
    ): array {
        $data = ['value' => $value, 'format' => $format];
        if ($currency !== null) {
            $data['currency'] = $currency;
            $data['display'] = Money::fromCents($value);
        }

        return [
            'type' => 'stat',
            'key' => $key,
            'title' => $title,
            'data' => $data,
            'meta' => array_filter([
                'cta' => $cta,
            ]),
        ];
    }

    /**
     * Time series from daily_metrics. Returns one point per day in the range.
     */
    private function revenueTrendChart(string $from, string $to, ?int $tenantId = null, ?array $cta = null): array
    {
        // One series: the platform-wide rows (tenant_id NULL) for the super
        // admin, the store's own rows otherwise, never a mix. The tenant
        // scope is skipped because it would hide the platform rows.
        $rows = DailyMetric::query()->withoutGlobalScope('tenant')
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            // whereDate, not whereBetween: a stored "Y-m-d 00:00:00" would
            // sort after "$to" and drop today's bar.
            ->whereDate('recorded_on', '>=', $from)
            ->whereDate('recorded_on', '<=', $to)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'))
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'value']);

        return [
            'type' => 'chart',
            'key' => 'revenue_trend',
            'title' => __('Revenue trend'),
            'data' => [
                'kind' => 'line',
                'series' => [[
                    'name' => 'Revenue',
                    'points' => $rows->map(fn ($r) => [$r->recorded_on->toDateString(), (int) $r->value])->all(),
                ]],
            ],
            'meta' => array_filter(['cta' => $cta]),
        ];
    }

    private function recentOrdersTable(?int $tenantId = null, ?int $userId = null, ?string $title = null, ?array $cta = null): array
    {
        $query = Order::query()->latest()->limit(10);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $rows = $query->get(['id', 'order_number', 'total', 'currency', 'status', 'created_at'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'total' => (string) $o->total,
                'currency' => $o->currency,
                'status' => $o->status,
                'created_at' => $o->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'type' => 'table',
            'key' => 'recent_orders',
            'title' => $title ?? __('Recent orders'),
            'data' => [
                'columns' => [
                    ['key' => 'order_number', 'label' => __('#')],
                    ['key' => 'total', 'label' => __('Total'), 'align' => 'right', 'format' => 'money'],
                    ['key' => 'status', 'label' => __('Status')],
                    ['key' => 'created_at', 'label' => __('Date')],
                ],
                'rows' => $rows,
            ],
            'meta' => array_filter(['cta' => $cta]),
        ];
    }

    /**
     * Relative URL of a named route, or null if the app has no such page,
     * so the dashboard never links to a 404. Features that land later
     * (vendor approval, payouts, downloads) light their links up by name.
     *
     * @param  array<string, mixed>  $params
     */
    private function link(string $routeName, array $params = []): ?string
    {
        return Route::has($routeName) ? route($routeName, $params, false) : null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{href: string, label: string}|null
     */
    private function cta(string $routeName, string $label, array $params = []): ?array
    {
        $href = $this->link($routeName, $params);

        return $href === null ? null : ['href' => $href, 'label' => $label];
    }

    private function quickActions(array $actions): array
    {
        return [
            'type' => 'quick-actions',
            'key' => 'quick_actions',
            'title' => __('Quick actions'),
            // Actions whose page doesn't exist (yet) are left out.
            'data' => ['actions' => array_values(array_filter($actions, fn (array $a) => $a['href'] !== null))],
        ];
    }

    /* ──────────────────────────────────────────────────────────────── */
    /*  HELPERS */
    /* ──────────────────────────────────────────────────────────────── */

    private function cacheKey(string $layout, ?int $tenantId, int $userId): string
    {
        $version = (int) Cache::get('cache:dashboard:version', 1);
        // Locale in the key: widget titles are translated (lang/{locale}.json).
        $locale = app()->getLocale();

        return "dashboard:tenant:{$tenantId}:role:{$layout}:user:{$userId}:{$locale}:v{$version}";
    }

    /**
     * Run a count() that might reference a table that doesn't exist yet
     * (e.g. payouts in a fresh install). Returns 0 instead of throwing
     * so the dashboard renders for everyone from day one.
     */
    private function safeCount(\Closure $fn): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeSum(\Closure $fn): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
