<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Download;
use App\Models\License;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Store Admin',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $customers = User::factory()->count(9)->create();

        $categoryTree = [
            'Laravel Scripts' => ['CRM Scripts', 'Ecommerce Scripts'],
            'SaaS Products' => ['Analytics SaaS', 'AI Tools'],
            'APIs' => ['Payment APIs', 'Data APIs'],
            'Templates' => ['Admin Templates', 'Landing Pages'],
            'Licenses' => [],
        ];

        $rootCategories = collect();
        foreach ($categoryTree as $name => $children) {
            $root = Category::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "Curated $name for makers and teams.",
            ]);
            $rootCategories->push($root);

            foreach ($children as $childName) {
                Category::factory()->create([
                    'parent_id' => $root->id,
                    'name' => $childName,
                    'slug' => Str::slug($childName),
                ]);
            }
        }

        $allCategories = Category::all();

        $products = collect();
        $products = $products->merge(
            Product::factory()->count(6)->digitalDownload()->state(fn () => [
                'category_id' => $allCategories->random()->id,
            ])->create()
        );
        $products = $products->merge(
            Product::factory()->count(5)->subscription()->state(fn () => [
                'category_id' => $allCategories->random()->id,
            ])->create()
        );
        $products = $products->merge(
            Product::factory()->count(4)->apiAccess()->state(fn () => [
                'category_id' => $allCategories->random()->id,
            ])->create()
        );
        $products = $products->merge(
            Product::factory()->count(6)->license()->state(fn () => [
                'category_id' => $allCategories->random()->id,
            ])->create()
        );
        $products = $products->merge(
            Product::factory()->count(3)->draft()->state(fn () => [
                'category_id' => $allCategories->random()->id,
            ])->create()
        );

        Product::query()->whereIn('id', $products->random(5)->pluck('id'))->update(['is_featured' => true]);

        $coupons = collect([
            Coupon::factory()->percentage(20)->create(['code' => 'LAUNCH20', 'description' => 'Launch sale — 20% off']),
            Coupon::factory()->percentage(50)->create(['code' => 'BLACKFRIDAY50', 'description' => 'Black Friday blowout']),
            Coupon::factory()->fixed(10)->create(['code' => 'WELCOME10', 'description' => '$10 off your first order']),
        ]);
        Coupon::factory()->count(2)->create();

        $publishedProducts = $products->where('status', Product::STATUS_PUBLISHED);

        foreach ($customers as $customer) {
            $orderCount = fake()->numberBetween(0, 3);

            for ($i = 0; $i < $orderCount; $i++) {
                $orderProducts = $publishedProducts->random(fake()->numberBetween(1, 3));
                $subtotal = 0;

                $order = Order::factory()->paid()->create([
                    'user_id' => $customer->id,
                    'billing_email' => $customer->email,
                    'billing_name' => $customer->name,
                ]);

                foreach ($orderProducts as $product) {
                    $quantity = 1;
                    $unit = (float) ($product->sale_price ?? $product->price);
                    $line = round($unit * $quantity, 2);
                    $subtotal += $line;

                    $item = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_title' => $product->title,
                        'product_type' => $product->type,
                        'quantity' => $quantity,
                        'unit_price' => $unit,
                        'total_price' => $line,
                    ]);

                    if ($product->type === Product::TYPE_LICENSE) {
                        License::factory()->create([
                            'user_id' => $customer->id,
                            'product_id' => $product->id,
                            'order_item_id' => $item->id,
                            'activation_limit' => $product->default_activation_limit,
                        ]);
                    }

                    if ($product->type === Product::TYPE_DIGITAL_DOWNLOAD) {
                        Download::factory()->create([
                            'user_id' => $customer->id,
                            'product_id' => $product->id,
                            'order_item_id' => $item->id,
                        ]);
                    }
                }

                $discount = fake()->boolean(30) ? round($subtotal * 0.1, 2) : 0;
                $total = round($subtotal - $discount, 2);
                $order->update([
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'coupon_id' => $discount > 0 ? $coupons->random()->id : null,
                ]);

                Payment::factory()->create([
                    'order_id' => $order->id,
                    'user_id' => $customer->id,
                    'amount' => $total,
                    'processed_at' => $order->paid_at,
                ]);
            }
        }

        $subscriptionProducts = $publishedProducts->where('type', Product::TYPE_SUBSCRIPTION);
        if ($subscriptionProducts->isNotEmpty()) {
            $customers->random(min(5, $customers->count()))->each(function (User $user) use ($subscriptionProducts) {
                Subscription::factory()->create([
                    'user_id' => $user->id,
                    'product_id' => $subscriptionProducts->random()->id,
                ]);
            });
        }

        $publishedProducts->each(function (Product $product) use ($customers) {
            $reviewers = $customers->shuffle()->take(fake()->numberBetween(0, 4));
            foreach ($reviewers as $reviewer) {
                Review::firstOrCreate(
                    ['user_id' => $reviewer->id, 'product_id' => $product->id],
                    Review::factory()->make([
                        'user_id' => $reviewer->id,
                        'product_id' => $product->id,
                    ])->getAttributes()
                );
            }
        });

        $customers->each(function (User $user) use ($publishedProducts) {
            $picks = $publishedProducts->random(min(3, $publishedProducts->count()));
            foreach ($picks as $product) {
                Wishlist::firstOrCreate([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                ]);
            }
        });

        BlogPost::factory()->count(6)->create(['user_id' => $admin->id]);
        BlogPost::factory()->count(2)->draft()->create(['user_id' => $admin->id]);
    }
}
