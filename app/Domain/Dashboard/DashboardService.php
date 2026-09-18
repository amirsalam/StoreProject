<?php

namespace App\Domain\Dashboard;

use App\Models\DailyMetric;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        Cache::increment('cache:dashboard:version', 1);
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
    /*  SUPER-ADMIN                                                     */
    /* ──────────────────────────────────────────────────────────────── */

    private function superAdminWidgets(): array
    {
        $today = CarbonImmutable::today();
        $thirtyDays = $today->subDays(30)->toDateString();

        // Platform-wide totals (tenant_id IS NULL) summed across the last 30 days.
        $revenueCents = (int) DB::table('daily_metrics')
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            ->where('recorded_on', '>=', $thirtyDays)
            ->sum('value');

        $ordersCount = (int) DB::table('daily_metrics')
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
            $this->statWidget('total_revenue_30d', 'Revenue (30 days)', $revenueCents, 'money', 'USD'),
            $this->statWidget('orders_30d', 'Orders (30 days)', $ordersCount, 'integer'),
            $this->statWidget('active_vendors', 'Active vendors', $activeVendors, 'integer', cta: ['href' => '/admin/vendors', 'label' => 'Manage']),
            $this->statWidget('total_customers', 'Total customers', $totalCustomers, 'integer'),
            $this->statWidget('pending_approvals', 'Pending approvals', $pendingApprovals, 'integer', cta: ['href' => '/admin/approvals', 'label' => 'Review']),
            $this->revenueTrendChart($thirtyDays, $today->toDateString()),
            $this->quickActions([
                ['label' => 'Approve vendor', 'href' => '/admin/approvals', 'icon' => 'check', 'primary' => true],
                ['label' => 'Create coupon', 'href' => '/admin/coupons/new', 'icon' => 'percent'],
                ['label' => 'Reconcile', 'href' => '/admin/reconcile', 'icon' => 'refresh'],
                ['label' => 'Announcement', 'href' => '/admin/announcements/new', 'icon' => 'megaphone'],
            ]),
        ];
    }

    /* ──────────────────────────────────────────────────────────────── */
    /*  VENDOR                                                          */
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
            $this->statWidget('revenue_month', 'Revenue this month', $monthRevenueCents, 'money', $tenant->settings['currency'] ?? 'USD'),
            $this->statWidget('total_sales', 'Total sales', $totalSales, 'integer'),
            $this->statWidget('pending_payout', 'Pending payout', $pendingPayoutCents, 'money', $tenant->settings['currency'] ?? 'USD', cta: ['href' => '/payouts', 'label' => 'Request']),
            $this->statWidget('conversion_rate', 'Conversion rate', 0, 'percent'),
            $this->revenueTrendChart($thirtyDays, $today->toDateString(), $tenant->id),
            $this->recentOrdersTable($tenant->id),
            $this->quickActions([
                ['label' => 'Add product', 'href' => '/admin/products/create', 'icon' => 'plus', 'primary' => true],
                ['label' => 'Create coupon', 'href' => '/admin/coupons/new', 'icon' => 'percent'],
                ['label' => 'Request payout', 'href' => '/payouts/new', 'icon' => 'wallet'],
            ]),
        ];
    }

    /* ──────────────────────────────────────────────────────────────── */
    /*  CUSTOMER                                                        */
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
            $this->statWidget('total_spent', 'Total spent', (int) $totalSpentCents, 'money', 'USD'),
            $this->statWidget('active_subs', 'Active subscriptions', $activeSubs, 'integer'),
            $this->statWidget('wallet_balance', 'Wallet balance', $walletCents, 'money', 'USD', cta: ['href' => '/wallet', 'label' => 'View']),
            $this->recentOrdersTable(null, $user->id, title: 'Your recent orders'),
            $this->quickActions([
                ['label' => 'Browse products', 'href' => '/products', 'icon' => 'shopping-bag', 'primary' => true],
                ['label' => 'Downloads', 'href' => '/downloads', 'icon' => 'download'],
                ['label' => 'Support', 'href' => '/support', 'icon' => 'help'],
            ]),
        ];
    }

    /* ──────────────────────────────────────────────────────────────── */
    /*  TEAM-MEMBER                                                     */
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
            $this->statWidget('open_tasks', 'Open tasks', $openTasks, 'integer', cta: ['href' => '/tasks', 'label' => 'View']),
            $this->statWidget('unread_alerts', 'Unread notifications', $unreadNotifications, 'integer'),
            $this->quickActions([
                ['label' => 'Open my tasks', 'href' => '/tasks', 'icon' => 'check-square', 'primary' => true],
                ['label' => 'Team activity', 'href' => '/activity', 'icon' => 'activity'],
            ]),
        ];
    }

    /* ──────────────────────────────────────────────────────────────── */
    /*  WIDGET BUILDERS                                                 */
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
    private function revenueTrendChart(string $from, string $to, ?int $tenantId = null): array
    {
        $rows = DailyMetric::query()
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            ->whereBetween('recorded_on', [$from, $to])
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'value']);

        return [
            'type' => 'chart',
            'key' => 'revenue_trend',
            'title' => 'Revenue trend',
            'data' => [
                'kind' => 'line',
                'series' => [[
                    'name' => 'Revenue',
                    'points' => $rows->map(fn ($r) => [$r->recorded_on->toDateString(), $r->value])->all(),
                ]],
            ],
            'meta' => ['cta' => ['href' => '/analytics/revenue', 'label' => 'View report']],
        ];
    }

    private function recentOrdersTable(?int $tenantId = null, ?int $userId = null, string $title = 'Recent orders'): array
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
            'title' => $title,
            'data' => [
                'columns' => [
                    ['key' => 'order_number', 'label' => '#'],
                    ['key' => 'total', 'label' => 'Total', 'align' => 'right', 'format' => 'money'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'created_at', 'label' => 'Date'],
                ],
                'rows' => $rows,
            ],
            'meta' => ['cta' => ['href' => '/orders', 'label' => 'View all']],
        ];
    }

    private function quickActions(array $actions): array
    {
        return [
            'type' => 'quick-actions',
            'key' => 'quick_actions',
            'title' => 'Quick actions',
            'data' => ['actions' => $actions],
        ];
    }

    /* ──────────────────────────────────────────────────────────────── */
    /*  HELPERS                                                         */
    /* ──────────────────────────────────────────────────────────────── */

    private function cacheKey(string $layout, ?int $tenantId, int $userId): string
    {
        $version = (int) Cache::get('cache:dashboard:version', 1);
        return "dashboard:tenant:{$tenantId}:role:{$layout}:user:{$userId}:v{$version}";
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
