<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Dashboard\DashboardService;
use App\Models\DailyMetric;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Every link on every dashboard layout must lead to a real page, and the
 * super-admin revenue figures must count each order once.
 */
class DashboardLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function layouts(): array
    {
        return [
            'super-admin' => ['super-admin'],
            'vendor' => ['vendor'],
            'team' => ['team'],
            'customer' => ['customer'],
        ];
    }

    #[DataProvider('layouts')]
    public function test_every_dashboard_link_resolves_to_a_route(string $layout): void
    {
        $payload = app(DashboardService::class)->forUser($this->userFor($layout));
        $this->assertSame($layout, $payload['layout']);

        $hrefs = [];
        array_walk_recursive($payload['widgets'], function ($value, $key) use (&$hrefs) {
            if ($key === 'href') {
                $hrefs[] = $value;
            }
        });

        $this->assertNotEmpty($hrefs, "{$layout} dashboard has no links at all");

        foreach ($hrefs as $href) {
            try {
                Route::getRoutes()->match(Request::create((string) $href, 'GET'));
            } catch (HttpException) {
                $this->fail("{$layout} dashboard links to {$href}, which has no route (404).");
            }
        }
    }

    public function test_super_admin_revenue_counts_each_order_once(): void
    {
        $tenant = Tenant::factory()->create();
        // The rollup writes the same money twice: per store and platform-wide.
        foreach ([null, $tenant->id] as $tenantId) {
            DailyMetric::create([
                'tenant_id' => $tenantId,
                'metric_key' => DailyMetric::KEY_REVENUE_CENTS,
                'dimension_key' => 'total',
                'value' => 4_500,
                'currency' => 'USD',
                'recorded_on' => now()->toDateString(),
            ]);
        }

        $widgets = collect(app(DashboardService::class)->forUser(User::factory()->admin()->create())['widgets']);

        $this->assertSame(4_500, $widgets->firstWhere('key', 'total_revenue_30d')['data']['value']);
        $this->assertSame(
            [[now()->toDateString(), 4_500]],
            $widgets->firstWhere('key', 'revenue_trend')['data']['series'][0]['points'],
        );
    }

    private function userFor(string $layout): User
    {
        return match ($layout) {
            'super-admin' => User::factory()->admin()->create(),
            'customer' => User::factory()->create(),
            default => $this->roleUser($layout === 'team' ? 'team-member' : 'vendor'),
        };
    }

    private function roleUser(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        // Vendor/team dashboards read the active store.
        $tenant = Tenant::factory()->forOwner($user)->create();
        app(TenantContext::class)->set($tenant);

        return $user;
    }
}
