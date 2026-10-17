<?php

namespace Tests\Feature\Marketplace;

use App\Events\PaymentCompleted;
use App\Models\Download;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\FileStorageSettings;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Large product files: with cloud storage on, the browser uploads straight
 * to the bucket through a signed URL, the product form attaches it with a
 * one-time token, and buyers download through a short-lived signed URL.
 * The bucket is faked — nothing leaves the test.
 */
class CloudStorageTest extends TestCase
{
    use RefreshDatabase;

    private FilesystemAdapter $bucket;

    public function test_admin_saves_storage_settings_with_an_encrypted_secret(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put(route('admin.storage.update'), [
            'enabled' => true,
            'provider' => 'gcs',
            'bucket' => 'store-files',
            'key' => 'GOOG1EXAMPLE',
            'secret' => 'hmac-secret-value',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNotSame('hmac-secret-value', Setting::get('storage.secret'));
        $this->assertSame('hmac-secret-value', Crypt::decryptString(Setting::get('storage.secret')));

        $config = app(FileStorageSettings::class)->diskConfig();
        $this->assertSame('https://storage.googleapis.com', $config['endpoint']);
        $this->assertSame('auto', $config['region']);

        // The page never gets the secret back.
        $this->actingAs($admin)->get(route('admin.storage.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.has_secret', true)
                ->missing('settings.secret'));
    }

    public function test_only_admins_manage_storage(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.storage.edit'))->assertForbidden();
    }

    public function test_the_product_form_switches_to_direct_upload_when_cloud_is_on(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.products.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('upload.mode', 'chunked')
                ->where('upload.max_bytes', 200 * 1024 * 1024));

        $this->enableCloud();

        $this->actingAs($admin)->get(route('admin.products.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('upload.mode', 'direct')
                ->where('upload.max_bytes', 5 * 1024 ** 3));
    }

    public function test_a_seller_uploads_a_large_file_straight_to_the_bucket(): void
    {
        $this->enableCloud();
        $seller = User::factory()->create();
        Vendor::factory()->create(['owner_user_id' => $seller->id]);

        // 1. Signed URL for a 150 MB file — far above PHP's upload limit.
        $signed = $this->actingAs($seller)->postJson(route('uploads.product-file'), [
            'name' => 'Big Course.zip',
            'size' => 150 * 1024 * 1024,
            'type' => 'application/zip',
        ])->assertOk()->json();

        $this->assertStringStartsWith('https://bucket.test/product-files/', $signed['url']);
        $this->assertSame('application/zip', $signed['headers']['Content-Type']);

        // 2. The browser PUTs the file to the bucket (simulated).
        $key = str_replace('https://bucket.test/', '', $signed['url']);
        $this->bucket->put($key, str_repeat('x', 2048));

        // 3. The product form attaches it with the token.
        $this->actingAs($seller)->post(route('workspace.products.store'), [
            ...$this->fields(),
            'download_file_token' => $signed['token'],
        ])->assertSessionHasNoErrors()->assertRedirect(route('workspace.products.index'));

        $product = Product::query()->where('slug', 'big-course')->firstOrFail();
        $this->assertSame(FileStorageSettings::DISK, $product->download_file_disk);
        $this->assertSame($key, $product->download_file_path);
        $this->assertSame('Big Course.zip', $product->download_file_name);
        $this->assertSame(2048, $product->download_file_size); // measured in the bucket, not trusted from the browser
    }

    public function test_a_token_only_works_for_the_user_it_was_issued_to(): void
    {
        $this->enableCloud();
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => true]);

        $signed = $this->actingAs($admin)->postJson(route('uploads.product-file'), ['name' => 'a.zip', 'size' => 10])->json();
        $this->bucket->put(str_replace('https://bucket.test/', '', $signed['url']), 'data');

        $this->actingAs($other)->post(route('admin.products.store'), [
            ...$this->fields(),
            'download_file_token' => $signed['token'],
        ])->assertSessionHasErrors('download_file');

        // Nothing half-saved.
        $this->assertFalse(Product::query()->where('slug', 'big-course')->exists());
    }

    public function test_an_upload_that_never_reached_the_bucket_is_refused(): void
    {
        $this->enableCloud();
        $admin = User::factory()->create(['is_admin' => true]);
        $signed = $this->actingAs($admin)->postJson(route('uploads.product-file'), ['name' => 'a.zip', 'size' => 10])->json();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            ...$this->fields(),
            'download_file_token' => $signed['token'],
        ])->assertSessionHasErrors('download_file');
    }

    public function test_signed_upload_urls_need_a_seller_or_admin(): void
    {
        $this->enableCloud();

        $this->actingAs(User::factory()->create())
            ->postJson(route('uploads.product-file'), ['name' => 'a.zip', 'size' => 10])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->postJson(route('uploads.product-file'), ['name' => 'huge.iso', 'size' => 6 * 1024 ** 3])
            ->assertStatus(422);
    }

    public function test_buyers_download_cloud_files_through_a_short_lived_link(): void
    {
        $this->enableCloud();
        $product = Product::factory()->digitalDownload()->create();
        $this->bucket->put('product-files/x/course.zip', 'zip-bytes');
        $product->forceFill([
            'download_file_disk' => FileStorageSettings::DISK,
            'download_file_path' => 'product-files/x/course.zip',
            'download_file_name' => 'course.zip',
        ])->save();

        $buyer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $buyer->id, 'status' => Order::STATUS_PAID, 'paid_at' => now()]);
        $order->items()->create([
            'product_id' => $product->id, 'product_title' => $product->title, 'product_type' => $product->type,
            'quantity' => 1, 'unit_price' => 10, 'total_price' => 10,
        ]);
        PaymentCompleted::dispatch(Payment::factory()->create(['order_id' => $order->id, 'user_id' => $buyer->id]), $order);
        $download = Download::query()->where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($buyer)->get(route('purchases.download', $download))
            ->assertRedirect('https://bucket.test/download/product-files/x/course.zip');
        $this->assertSame(1, $download->fresh()->downloads_count);
    }

    private function enableCloud(): void
    {
        app(FileStorageSettings::class)->save([
            'enabled' => true,
            'provider' => 's3',
            'bucket' => 'store-files',
            'region' => 'eu-west-3',
            'key' => 'AKIAEXAMPLE',
            'secret' => 'secret',
        ]);

        $this->bucket = Storage::fake(FileStorageSettings::DISK);
        // A signed-URL stand-in (this Laravel version has no public setter).
        (fn () => $this->temporaryUploadUrlCallback = fn (string $path) => [
            'url' => 'https://bucket.test/'.$path,
            'headers' => ['Host' => 'bucket.test'],
        ])->call($this->bucket);
        $this->bucket->buildTemporaryUrlsUsing(fn (string $path) => 'https://bucket.test/download/'.$path);
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(): array
    {
        return [
            'title' => 'Big Course',
            'slug' => 'big-course',
            'type' => Product::TYPE_DIGITAL_DOWNLOAD,
            'price' => '49.00',
            'currency' => 'USD',
            'default_activation_limit' => 1,
            'status' => Product::STATUS_PUBLISHED,
        ];
    }
}
