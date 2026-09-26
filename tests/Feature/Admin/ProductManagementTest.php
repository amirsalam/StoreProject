<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Download;
use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_admin_product_index(): void
    {
        $this->get('/admin/products')->assertRedirect('/login');
    }

    public function test_non_admins_are_forbidden_from_admin_product_index(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/admin/products')
            ->assertForbidden();
    }

    public function test_admins_can_load_product_index(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get('/admin/products')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('admin/products/index')
                    ->has('products.data', 3)
            );
    }

    public function test_product_index_filters_by_search_and_status(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->create(['title' => 'Stripe Toolkit', 'status' => Product::STATUS_PUBLISHED]);
        Product::factory()->create(['title' => 'Other Thing', 'status' => Product::STATUS_DRAFT]);

        $this->actingAs($admin)
            ->get('/admin/products?search=Stripe&status=published')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->has('products.data', 1)
                    ->where('products.data.0.title', 'Stripe Toolkit')
            );
    }

    public function test_admins_see_the_create_form(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/create')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page->component('admin/products/create')
            );
    }

    public function test_admins_can_store_a_new_product(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->post('/admin/products', [
                'category_id' => $category->id,
                'title' => 'My New Script',
                'slug' => 'my-new-script',
                'short_description' => 'Short description here',
                'description' => 'Long description.',
                'type' => Product::TYPE_DIGITAL_DOWNLOAD,
                'price' => '49.00',
                'sale_price' => null,
                'currency' => 'USD',
                'thumbnail' => null,
                'version' => '1.0.0',
                'license_type' => null,
                'default_activation_limit' => 1,
                'download_limit' => null,
                'status' => Product::STATUS_PUBLISHED,
                'is_featured' => false,
                'seo_title' => null,
                'seo_description' => null,
            ])
            ->assertRedirect('/admin/products')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'slug' => 'my-new-script',
            'title' => 'My New Script',
            'status' => Product::STATUS_PUBLISHED,
        ]);
    }

    public function test_required_fields_are_validated_on_store(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/products', [])
            ->assertSessionHasErrors([
                'title',
                'type',
                'price',
                'currency',
                'default_activation_limit',
                'status',
            ]);
    }

    public function test_duplicate_slugs_are_rejected_on_store(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->create(['slug' => 'taken-slug']);

        $this->actingAs($admin)
            ->post('/admin/products', [
                'title' => 'Anything',
                'slug' => 'taken-slug',
                'type' => Product::TYPE_DIGITAL_DOWNLOAD,
                'price' => '10',
                'currency' => 'USD',
                'default_activation_limit' => 1,
                'status' => Product::STATUS_DRAFT,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_sale_price_must_be_lower_than_price(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/products', [
                'title' => 'Bad Sale',
                'slug' => 'bad-sale',
                'type' => Product::TYPE_DIGITAL_DOWNLOAD,
                'price' => '10',
                'sale_price' => '10',
                'currency' => 'USD',
                'default_activation_limit' => 1,
                'status' => Product::STATUS_DRAFT,
            ])
            ->assertSessionHasErrors('sale_price');
    }

    public function test_admins_see_the_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->get("/admin/products/{$product->id}/edit")
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('admin/products/edit')
                    ->where('product.id', $product->id)
            );
    }

    public function test_admins_can_update_a_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'title' => 'Original Title',
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)
            ->put("/admin/products/{$product->id}", [
                'category_id' => $product->category_id,
                'title' => 'Updated Title',
                'slug' => $product->slug,
                'short_description' => null,
                'description' => null,
                'type' => $product->type,
                'price' => '99.00',
                'sale_price' => null,
                'currency' => 'USD',
                'thumbnail' => null,
                'version' => null,
                'license_type' => null,
                'default_activation_limit' => 1,
                'download_limit' => null,
                'status' => Product::STATUS_PUBLISHED,
                'is_featured' => true,
                'seo_title' => null,
                'seo_description' => null,
            ])
            ->assertRedirect('/admin/products');

        $product->refresh();

        $this->assertSame('Updated Title', $product->title);
        $this->assertSame(Product::STATUS_PUBLISHED, $product->status);
        $this->assertTrue($product->is_featured);
        $this->assertSame(99.00, (float) $product->price);
    }

    public function test_admins_can_delete_a_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->delete("/admin/products/{$product->id}")
            ->assertRedirect('/admin/products');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_deleting_a_sold_product_archives_it_and_keeps_purchase_history(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->digitalDownload()->create(['status' => Product::STATUS_PUBLISHED]);
        $order = Order::factory()->create();
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_type' => $product->type,
            'quantity' => 1,
            'unit_price' => $product->price,
            'total_price' => $product->price,
        ]);
        Download::factory()->create(['product_id' => $product->id, 'order_item_id' => $item->id, 'user_id' => $order->user_id]);

        $this->actingAs($admin)
            ->delete("/admin/products/{$product->id}")
            ->assertRedirect('/admin/products')
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'archived instead of deleted'));

        $this->assertSame(Product::STATUS_ARCHIVED, $product->refresh()->status);
        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('downloads', ['product_id' => $product->id]);
    }

    public function test_a_product_with_only_a_license_is_archived_not_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->license()->create();
        License::factory()->create(['product_id' => $product->id]);

        $this->actingAs($admin)->delete("/admin/products/{$product->id}")->assertRedirect('/admin/products');

        $this->assertSame(Product::STATUS_ARCHIVED, $product->refresh()->status);
    }

    public function test_non_admins_cannot_mutate_products(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->post('/admin/products', [])
            ->assertForbidden();

        $this->actingAs($user)
            ->put("/admin/products/{$product->id}", [])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete("/admin/products/{$product->id}")
            ->assertForbidden();
    }
}
