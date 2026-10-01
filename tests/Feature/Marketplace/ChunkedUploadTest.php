<?php

namespace Tests\Feature\Marketplace;

use App\Domain\Marketplace\ProductFileService;
use App\Models\Product;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Without cloud storage, product files still reach 200 MB: the browser
 * sends them in chunks smaller than PHP's upload limit and the server
 * reassembles them.
 */
class ChunkedUploadTest extends TestCase
{
    use RefreshDatabase;

    private FilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = Storage::fake(ProductFileService::DISK);
    }

    public function test_a_file_sent_in_chunks_is_reassembled_and_attached(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $parts = ['first-part|', 'second-part|', 'last'];
        $size = strlen(implode('', $parts));

        $session = $this->actingAs($admin)->postJson(route('uploads.product-file'), ['name' => 'Course.zip', 'size' => $size])
            ->assertOk()
            ->json();
        $this->assertSame('chunked', $session['mode']);
        $this->assertGreaterThan(0, $session['chunk_size']);

        foreach ($parts as $index => $part) {
            $this->sendChunk($admin, $session['token'], $index, $part)->assertOk();
        }

        $this->actingAs($admin)->post(route('admin.products.store'), [
            ...$this->fields(),
            'download_file_token' => $session['token'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $product = Product::query()->where('slug', 'course')->firstOrFail();
        $this->assertSame('Course.zip', $product->download_file_name);
        $this->assertSame($size, $product->download_file_size);
        $this->assertSame(implode('', $parts), $this->disk->get($product->download_file_path));
        $this->assertSame([], $this->disk->files('upload-chunks')); // temp file moved, not copied
    }

    public function test_up_to_200_mb_is_accepted_and_more_is_refused(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->postJson(route('uploads.product-file'), ['name' => 'big.zip', 'size' => 200 * 1024 * 1024])
            ->assertOk();
        $this->actingAs($admin)->postJson(route('uploads.product-file'), ['name' => 'huge.zip', 'size' => 200 * 1024 * 1024 + 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('size');
    }

    public function test_chunks_must_arrive_in_order_and_fit_the_announced_size(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $this->actingAs($admin)->postJson(route('uploads.product-file'), ['name' => 'a.zip', 'size' => 10])->json('token');

        $this->sendChunk($admin, $token, 1, 'abc')->assertStatus(422);          // skipped chunk 0
        $this->sendChunk($admin, $token, 0, 'abcdefghijk')->assertStatus(422);  // 11 bytes > 10 announced
    }

    public function test_an_unfinished_upload_cannot_be_attached(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $this->actingAs($admin)->postJson(route('uploads.product-file'), ['name' => 'a.zip', 'size' => 10])->json('token');
        $this->sendChunk($admin, $token, 0, 'abc')->assertOk();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            ...$this->fields(),
            'download_file_token' => $token,
        ])->assertSessionHasErrors('download_file');

        $this->assertFalse(Product::query()->where('slug', 'course')->exists());
    }

    public function test_only_the_uploader_can_send_chunks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $this->actingAs($admin)->postJson(route('uploads.product-file'), ['name' => 'a.zip', 'size' => 10])->json('token');

        $this->sendChunk(User::factory()->create(['is_admin' => true]), $token, 0, 'abc')->assertStatus(422);
        $this->sendChunk(User::factory()->create(), $token, 0, 'abc')->assertForbidden();
    }

    private function sendChunk(User $user, string $token, int $index, string $bytes)
    {
        return $this->actingAs($user)->post(route('uploads.product-file.chunk', $token), [
            'index' => $index,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $bytes),
        ], ['Accept' => 'application/json']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(): array
    {
        return [
            'title' => 'Course',
            'slug' => 'course',
            'type' => Product::TYPE_DIGITAL_DOWNLOAD,
            'price' => '49.00',
            'currency' => 'USD',
            'default_activation_limit' => 1,
            'status' => Product::STATUS_PUBLISHED,
        ];
    }
}
