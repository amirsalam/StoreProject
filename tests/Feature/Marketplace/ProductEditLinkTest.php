<?php

namespace Tests\Feature\Marketplace;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The product page offers "Edit prices & options" to the people who can
 * change them — admins and the selling store's owner — and nobody else.
 */
class ProductEditLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_and_the_seller_get_an_edit_link(): void
    {
        $seller = User::factory()->create();
        $vendor = Vendor::factory()->create(['owner_user_id' => $seller->id]);
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'status' => Product::STATUS_PUBLISHED]);
        $url = route('products.show', $product->slug);

        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('editUrl', null));

        $this->actingAs(User::factory()->create())->get($url)
            ->assertInertia(fn (Assert $page) => $page->where('editUrl', null));

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get($url)
            ->assertInertia(fn (Assert $page) => $page->where('editUrl', route('admin.products.edit', $product)));

        $this->actingAs($seller)->get($url)
            ->assertInertia(fn (Assert $page) => $page->where('editUrl', route('workspace.products.edit', $product)));
    }

    public function test_a_seller_gets_no_link_on_another_stores_product(): void
    {
        $seller = User::factory()->create();
        Vendor::factory()->create(['owner_user_id' => $seller->id]);
        $other = Product::factory()->create(['vendor_id' => Vendor::factory()->create()->id, 'status' => Product::STATUS_PUBLISHED]);

        $this->actingAs($seller)->get(route('products.show', $other->slug))
            ->assertInertia(fn (Assert $page) => $page->where('editUrl', null));
    }
}
