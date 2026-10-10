<?php

namespace Tests\Feature\Payments;

use App\Domain\Billing\StripeGateway;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * stripe-php reports Stripe's advisory `Stripe-Notice` header with
 * trigger_error(E_USER_WARNING); Laravel would turn that into an exception
 * after the payment already succeeded at Stripe. StripeGateway::call()
 * logs it instead.
 */
class StripeNoticeTest extends TestCase
{
    public function test_a_stripe_notice_is_logged_not_thrown(): void
    {
        Log::spy();

        $result = (new StripeGateway)->call(function () {
            trigger_error('You are using an outdated API version (2024-04-10).', E_USER_WARNING);

            return 'pi_created';
        });

        $this->assertSame('pi_created', $result);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_contains($message, 'outdated API version'));
    }

    public function test_real_errors_inside_a_call_still_throw(): void
    {
        $this->expectException(\RuntimeException::class);

        (new StripeGateway)->call(fn () => throw new \RuntimeException('network down'));
    }

    public function test_the_error_handler_is_restored_afterwards(): void
    {
        (new StripeGateway)->call(fn () => null);

        $this->expectException(\ErrorException::class);
        trigger_error('outside a Stripe call', E_USER_WARNING);
    }
}
