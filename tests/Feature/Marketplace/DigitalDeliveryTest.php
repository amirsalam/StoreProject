<?php

namespace Tests\Feature\Marketplace;

use App\Domain\Marketplace\ProductFileService;
use App\Events\PaymentCompleted;
use App\Models\Download;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * From upload to delivery: the product file is stored privately, a paid
 * order grants a download + license key, and the buyer — only the buyer —
 * gets them from My purchases.
 */
class DigitalDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ProductFileService::DISK);
    }

    public function test_admin_uploads_a_product_file_that_stays_private(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            ...$this->productFields(),
            'download_file' => UploadedFile::fake()->create('My Theme v1.zip', 512, 'application/zip'),
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::query()->where('slug', 'ui-kit')->firstOrFail();
        $this->assertSame('My Theme v1.zip', $product->download_file_name);
        $this->assertSame(512 * 1024, $product->download_file_size);
        Storage::disk(ProductFileService::DISK)->assertExists($product->download_file_path);

        // The storage path never reaches a browser.
        $this->assertArrayNotHasKey('download_file_path', $product->toArray());
        $this->actingAs($admin)->get(route('admin.products.edit', $product))
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.download_file_name', 'My Theme v1.zip')
                ->missing('product.download_file_path'));
    }

    public function test_replacing_and_removing_the_file_cleans_up_storage(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::factory()->digitalDownload()->create(['slug' => 'ui-kit']);
        app(ProductFileService::class)->replace($product, UploadedFile::fake()->create('v1.zip', 10));
        $first = $product->fresh()->download_file_path;

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            ...$this->productFields(),
            'download_file' => UploadedFile::fake()->create('v2.zip', 20),
        ])->assertRedirect();

        $second = $product->fresh()->download_file_path;
        Storage::disk(ProductFileService::DISK)->assertMissing($first);
        Storage::disk(ProductFileService::DISK)->assertExists($second);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            ...$this->productFields(),
            'remove_download_file' => '1',
        ])->assertRedirect();

        Storage::disk(ProductFileService::DISK)->assertMissing($second);
        $this->assertNull($product->fresh()->download_file_path);
    }

    public function test_a_licensed_product_with_a_file_delivers_both_key_and_download(): void
    {
        $product = Product::factory()->license()->create(['default_activation_limit' => 2, 'download_limit' => 3, 'sales_count' => 0]);
        [$buyer, $order] = $this->paidOrderFor($product);

        $item = $order->items->first();
        $this->assertSame(1, License::query()->where('order_item_id', $item->id)->count());
        $this->assertSame(1, Download::query()->where('order_item_id', $item->id)->count());
        // The sale is counted once, not once per grant.
        $this->assertSame(1, $item->product->fresh()->sales_count);

        $license = License::query()->where('order_item_id', $item->id)->first();

        $this->actingAs($buyer)->get(route('purchases.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchases/index')
                ->has('purchases.data', 1)
                ->where('purchases.data.0.licenses.0.key', $license->license_key)
                ->where('purchases.data.0.licenses.0.activation_limit', 2)
                ->where('purchases.data.0.download.available', true)
                ->where('purchases.data.0.download.max', 3)
                ->where('purchases.data.0.download.file_name', 'product.zip'));
    }

    public function test_the_buyer_downloads_the_file_and_the_limit_is_enforced(): void
    {
        [$buyer, $order] = $this->paidOrderFor(Product::factory()->digitalDownload()->create(['download_limit' => 1]));
        $download = Download::query()->where('order_item_id', $order->items->first()->id)->firstOrFail();

        $response = $this->actingAs($buyer)->get(route('purchases.download', $download));
        $response->assertOk();
        $this->assertStringContainsString('product.zip', (string) $response->headers->get('content-disposition'));
        $this->assertSame(1, $download->fresh()->downloads_count);
        $this->assertNotNull($download->fresh()->last_downloaded_at);

        // Limit reached: back to My purchases with a message, no file.
        $this->actingAs($buyer)->get(route('purchases.download', $download))
            ->assertRedirect(route('purchases.index'))
            ->assertSessionHas('error');
        $this->assertSame(1, $download->fresh()->downloads_count);
    }

    public function test_nobody_else_can_download_a_buyers_file(): void
    {
        [, $order] = $this->paidOrderFor(Product::factory()->digitalDownload()->create());
        $download = Download::query()->where('order_item_id', $order->items->first()->id)->firstOrFail();

        $this->get(route('purchases.download', $download))->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get(route('purchases.download', $download))
            ->assertNotFound();
        $this->assertSame(0, $download->fresh()->downloads_count);
    }

    public function test_a_missing_file_is_reported_instead_of_erroring(): void
    {
        [$buyer, $order] = $this->paidOrderFor(Product::factory()->digitalDownload()->create(), withFile: false);
        $download = Download::query()->where('order_item_id', $order->items->first()->id)->firstOrFail();

        $this->actingAs($buyer)->get(route('purchases.index'))
            ->assertInertia(fn (Assert $page) => $page->where('purchases.data.0.download.available', false));

        $this->actingAs($buyer)->get(route('purchases.download', $download))
            ->assertRedirect(route('purchases.index'))
            ->assertSessionHas('error');
    }

    public function test_my_purchases_only_lists_the_users_own_paid_orders(): void
    {
        $this->paidOrderFor(Product::factory()->digitalDownload()->create());
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('purchases.index'))
            ->assertInertia(fn (Assert $page) => $page->has('purchases.data', 0));
    }

    public function test_the_confirmation_email_links_to_my_purchases(): void
    {
        [, $order] = $this->paidOrderFor(Product::factory()->license()->create());

        $mail = (new OrderConfirmation($order->fresh()))->toMail(new AnonymousNotifiable);

        $this->assertSame(route('purchases.index'), $mail->actionUrl);
        $this->assertStringContainsString(route('purchases.index'), (string) $mail->render());
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function paidOrderFor(Product $product, bool $withFile = true): array
    {
        if ($withFile) {
            app(ProductFileService::class)->replace($product, UploadedFile::fake()->create('product.zip', 100));
        }

        $buyer = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => Order::STATUS_PAID,
            'subtotal' => 19.00,
            'discount' => 0,
            'total' => 19.00,
            'currency' => 'USD',
            'billing_name' => 'Ada Lovelace',
            'billing_email' => 'ada@example.test',
            'paid_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_type' => $product->type,
            'quantity' => 1,
            'unit_price' => 19.00,
            'total_price' => 19.00,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $buyer->id,
            'amount' => 19.00,
            'status' => Payment::STATUS_SUCCEEDED,
        ]);

        PaymentCompleted::dispatch($payment, $order);

        return [$buyer, $order->fresh('items.product')];
    }

    /**
     * @return array<string, mixed>
     */
    private function productFields(): array
    {
        return [
            'title' => 'UI Kit',
            'slug' => 'ui-kit',
            'type' => Product::TYPE_DIGITAL_DOWNLOAD,
            'price' => '19.00',
            'currency' => 'USD',
            'default_activation_limit' => 1,
            'status' => Product::STATUS_PUBLISHED,
        ];
    }
}
