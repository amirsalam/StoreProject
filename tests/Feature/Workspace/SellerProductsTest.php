<?php

namespace Tests\Feature\Workspace;

use App\Domain\Marketplace\ProductFileService;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Sellers manage their own store's products (with the file buyers get)
 * and see their own sales — never another seller's.
 */
class SellerProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ProductFileService::DISK);
    }

    public function test_a_seller_creates_a_product_in_their_own_store(): void
    {
        [$seller, $vendor] = $this->seller();

        $this->actingAs($seller)->post(route('workspace.products.store'), [
            ...$this->fields(),
            'is_featured' => '1', // sellers can't feature — ignored
            'download_file' => UploadedFile::fake()->create('theme.zip', 50),
        ])->assertRedirect(route('workspace.products.index'));

        $product = Product::query()->where('slug', 'my-theme')->firstOrFail();
        $this->assertSame($vendor->id, $product->vendor_id);
        $this->assertFalse($product->is_featured);
        $this->assertSame('theme.zip', $product->download_file_name);
        Storage::disk(ProductFileService::DISK)->assertExists($product->download_file_path);
    }

    public function test_the_list_shows_only_the_sellers_products(): void
    {
        [$seller, $vendor] = $this->seller();
        Product::factory()->create(['vendor_id' => $vendor->id, 'title' => 'Mine']);
        Product::factory()->create(['vendor_id' => Vendor::factory()->create()->id, 'title' => 'Theirs']);
        Product::factory()->create(['vendor_id' => null, 'title' => 'Platform']);

        $this->actingAs($seller)->get(route('workspace.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspace/products/index')
                ->has('products.data', 1)
                ->where('products.data.0.title', 'Mine'));
    }

    public function test_a_seller_cannot_touch_another_sellers_product(): void
    {
        [$seller] = $this->seller();
        $other = Product::factory()->create(['vendor_id' => Vendor::factory()->create()->id, 'title' => 'Theirs']);

        $this->actingAs($seller)->get(route('workspace.products.edit', $other))->assertNotFound();
        $this->actingAs($seller)->put(route('workspace.products.update', $other), $this->fields())->assertNotFound();
        $this->actingAs($seller)->delete(route('workspace.products.destroy', $other))->assertNotFound();

        $this->assertSame('Theirs', $other->fresh()->title);
    }

    public function test_sellers_cannot_create_subscription_products(): void
    {
        [$seller] = $this->seller();

        $this->actingAs($seller)->post(route('workspace.products.store'), [
            ...$this->fields(),
            'type' => Product::TYPE_SUBSCRIPTION,
        ])->assertSessionHasErrors('type');
    }

    public function test_users_without_a_store_are_sent_to_open_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('workspace.products.index'))
            ->assertInertia(fn (Assert $page) => $page->where('vendor', null)->where('products', null));
        $this->actingAs($user)->get(route('workspace.products.create'))->assertRedirect(route('workspace.vendor.edit'));
        $this->actingAs($user)->post(route('workspace.products.store'), $this->fields())->assertForbidden();
    }

    public function test_sales_show_only_paid_orders_of_the_sellers_products(): void
    {
        [$seller, $vendor] = $this->seller();
        $mine = Product::factory()->create(['vendor_id' => $vendor->id]);
        $theirs = Product::factory()->create(['vendor_id' => Vendor::factory()->create()->id]);

        $this->orderFor($mine, Order::STATUS_PAID, 30.00);
        $this->orderFor($mine, Order::STATUS_PENDING, 99.00);
        $this->orderFor($theirs, Order::STATUS_PAID, 50.00);

        $this->actingAs($seller)->get(route('workspace.sales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspace/sales/index')
                ->has('sales.data', 1)
                ->where('summary.revenue', '30.00')
                ->where('summary.orders', 1));
    }

    /**
     * @return array{0: User, 1: Vendor}
     */
    private function seller(): array
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create(['owner_user_id' => $user->id]);

        return [$user, $vendor];
    }

    private function orderFor(Product $product, string $status, float $total): void
    {
        $order = Order::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'total' => $total,
            'billing_name' => 'Buyer',
            'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_type' => $product->type,
            'quantity' => 1,
            'unit_price' => $total,
            'total_price' => $total,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(): array
    {
        return [
            'title' => 'My Theme',
            'slug' => 'my-theme',
            'type' => Product::TYPE_LICENSE,
            'price' => '29.00',
            'currency' => 'USD',
            'default_activation_limit' => 1,
            'status' => Product::STATUS_PUBLISHED,
        ];
    }
}
