<?php

namespace Tests\Feature\Payments;

use App\Events\PaymentCompleted;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A paid order emails the buyer a receipt with their license keys and
 * downloads, at the billing email given at checkout.
 */
class OrderConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_paid_order_emails_the_buyer_once_with_their_license_key(): void
    {
        Notification::fake();
        [$order, $payment] = $this->paidOrder();

        PaymentCompleted::dispatch($payment, $order);

        $license = License::query()->where('order_item_id', $order->items->first()->id)->firstOrFail();

        Notification::assertSentOnDemand(
            OrderConfirmation::class,
            function (OrderConfirmation $notification, array $channels, AnonymousNotifiable $notifiable) use ($order, $license) {
                $html = (string) $notification->toMail($notifiable)->render();

                return $notifiable->routes['mail'] === ['ada@example.test' => 'Ada Lovelace']
                    && $notification->order->is($order)
                    && str_contains($html, $license->license_key)
                    && str_contains($html, $order->order_number)
                    && str_contains($html, '$25.00');
            },
        );
        Notification::assertSentOnDemandTimes(OrderConfirmation::class, 1);
    }

    public function test_the_email_is_written_in_the_buyers_language(): void
    {
        [$order] = $this->paidOrder();
        App::setLocale('ar');

        $mail = (new OrderConfirmation($order))->toMail(new AnonymousNotifiable);

        $this->assertSame("تم تأكيد طلبك {$order->order_number}", $mail->subject);
    }

    public function test_a_mail_failure_never_undoes_the_payment(): void
    {
        config(['mail.default' => 'broken', 'mail.mailers.broken' => ['transport' => 'does-not-exist']]);
        [$order, $payment] = $this->paidOrder();

        PaymentCompleted::dispatch($payment, $order);

        // Fulfillment still happened.
        $this->assertSame(1, License::query()->where('order_item_id', $order->items->first()->id)->count());
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    private function paidOrder(): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->license()->create(['default_activation_limit' => 3]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PAID,
            'subtotal' => 25.00,
            'discount' => 0,
            'total' => 25.00,
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
            'unit_price' => 25.00,
            'total_price' => 25.00,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => 25.00,
            'status' => Payment::STATUS_SUCCEEDED,
        ]);

        return [$order->fresh(), $payment];
    }
}
