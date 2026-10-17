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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A product can ship two files: the product file (Regular License) and the
 * Extended License's own file. Each buyer downloads the one for the
 * license they paid for.
 */
class ExtendedLicenseFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ProductFileService::DISK);
    }

    public function test_admin_uploads_a_regular_and_an_extended_file(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'title' => 'Theme', 'slug' => 'theme', 'type' => Product::TYPE_LICENSE, 'price' => '59',
            'currency' => 'USD', 'default_activation_limit' => 1, 'status' => Product::STATUS_PUBLISHED,
            'download_file' => UploadedFile::fake()->createWithContent('theme.zip', 'regular'),
            'extended_file' => UploadedFile::fake()->createWithContent('theme-extended.zip', 'extended + sources'),
        ])->assertSessionHasNoErrors();

        $product = Product::query()->where('slug', 'theme')->firstOrFail();
        $this->assertSame('theme.zip', $product->download_file_name);
        $this->assertSame('theme-extended.zip', $product->extended_file_name);
        Storage::disk(ProductFileService::DISK)->assertExists($product->extended_file_path);
        $this->assertArrayNotHasKey('extended_file_path', $product->toArray()); // never sent to a browser

        // Removing only the Extended file.
        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'title' => 'Theme', 'slug' => 'theme', 'type' => Product::TYPE_LICENSE, 'price' => '59',
            'currency' => 'USD', 'default_activation_limit' => 1, 'status' => Product::STATUS_PUBLISHED,
            'remove_extended_file' => '1',
        ])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertNull($product->extended_file_path);
        $this->assertSame('theme.zip', $product->download_file_name);
    }

    public function test_each_buyer_downloads_the_file_for_their_license(): void
    {
        $product = $this->productWithBothFiles();

        [$regularBuyer, $regularDownload] = $this->buy($product, License::TIER_REGULAR);
        [$extendedBuyer, $extendedDownload] = $this->buy($product, License::TIER_EXTENDED);

        $regular = $this->actingAs($regularBuyer)->get(route('purchases.download', $regularDownload));
        $this->assertStringContainsString('theme.zip', (string) $regular->headers->get('content-disposition'));
        $this->assertSame('regular', $regular->streamedContent());

        $extended = $this->actingAs($extendedBuyer)->get(route('purchases.download', $extendedDownload));
        $this->assertStringContainsString('theme-extended.zip', (string) $extended->headers->get('content-disposition'));
        $this->assertSame('extended + sources', $extended->streamedContent());

        $this->actingAs($extendedBuyer)->get(route('purchases.index'))
            ->assertInertia(fn (Assert $page) => $page->where('purchases.data.0.download.file_name', 'theme-extended.zip'));
    }

    public function test_extended_buyers_get_the_product_file_when_there_is_no_extended_one(): void
    {
        $product = Product::factory()->license()->create(['extended_price' => 159]);
        app(ProductFileService::class)->replace($product, UploadedFile::fake()->createWithContent('theme.zip', 'regular'));

        [$buyer, $download] = $this->buy($product, License::TIER_EXTENDED);

        $response = $this->actingAs($buyer)->get(route('purchases.download', $download));
        $this->assertSame('regular', $response->streamedContent());
    }

    private function productWithBothFiles(): Product
    {
        $product = Product::factory()->license()->create(['extended_price' => 159]);
        $files = app(ProductFileService::class);
        $files->replace($product, UploadedFile::fake()->createWithContent('theme.zip', 'regular'));
        $files->replace($product, UploadedFile::fake()->createWithContent('theme-extended.zip', 'extended + sources'), ProductFileService::EXTENDED);

        return $product->fresh();
    }

    /**
     * @return array{0: User, 1: Download}
     */
    private function buy(Product $product, string $tier): array
    {
        $buyer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $buyer->id, 'status' => Order::STATUS_PAID, 'paid_at' => now()]);
        $item = $order->items()->create([
            'product_id' => $product->id, 'product_title' => $product->title, 'product_type' => $product->type,
            'quantity' => 1, 'unit_price' => 59, 'total_price' => 59,
            'metadata' => ['license' => $tier, 'extended_support' => false, 'support_months' => 6],
        ]);
        PaymentCompleted::dispatch(Payment::factory()->create(['order_id' => $order->id, 'user_id' => $buyer->id]), $order);

        return [$buyer, Download::query()->where('order_item_id', $item->id)->firstOrFail()];
    }
}
