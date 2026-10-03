<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\WalletService;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_creates_wallet_ledger_row_and_updates_balance(): void
    {
        $service = app(WalletService::class);
        $user = User::factory()->create();
        $wallet = $service->forUser($user, 'USD');

        $row = $service->credit($wallet, 4900, 'test:credit:1');

        $this->assertSame(4900, $row->amount_cents);
        $this->assertSame(LedgerTransaction::TYPE_CREDIT, $row->type);
        $this->assertSame(4900, $row->balance_after_cents);

        $wallet->refresh();
        $this->assertSame(4900, $wallet->balance_cents);
        $this->assertSame(1, $wallet->version);
    }

    public function test_credit_is_idempotent_via_idempotency_key(): void
    {
        $service = app(WalletService::class);
        $wallet = $service->forUser(User::factory()->create(), 'USD');

        $first = $service->credit($wallet, 1000, 'dup-key');
        $second = $service->credit($wallet, 1000, 'dup-key');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, LedgerTransaction::query()->count());

        $wallet->refresh();
        $this->assertSame(1000, $wallet->balance_cents);
    }

    public function test_debit_subtracts_from_balance(): void
    {
        $service = app(WalletService::class);
        $wallet = $service->forUser(User::factory()->create(), 'USD');

        $service->credit($wallet, 10_000, 'c1');
        $service->debit($wallet, 3_000, 'd1');

        $wallet->refresh();
        $this->assertSame(7_000, $wallet->balance_cents);
        $this->assertSame(7_000, $service->computedBalance($wallet));
    }

    public function test_computed_balance_matches_wallet_balance(): void
    {
        $service = app(WalletService::class);
        $wallet = $service->forUser(User::factory()->create(), 'USD');

        $service->credit($wallet, 1500, 'a');
        $service->credit($wallet, 2500, 'b');
        $service->refund($wallet, 500, 'r1');
        $service->debit($wallet, 1000, 'd1');

        $wallet->refresh();
        $this->assertSame(3500, $wallet->balance_cents);
        $this->assertSame($wallet->balance_cents, $service->computedBalance($wallet));
    }

    public function test_credit_rejects_zero_or_negative(): void
    {
        $service = app(WalletService::class);
        $wallet = $service->forUser(User::factory()->create(), 'USD');

        $this->expectException(\InvalidArgumentException::class);
        $service->credit($wallet, 0, 'bad');
    }

    public function test_for_user_is_unique_per_currency(): void
    {
        $service = app(WalletService::class);
        $user = User::factory()->create();

        $usd1 = $service->forUser($user, 'USD');
        $usd2 = $service->forUser($user, 'USD');
        $eur = $service->forUser($user, 'EUR');

        $this->assertSame($usd1->id, $usd2->id);
        $this->assertNotSame($usd1->id, $eur->id);

        $this->assertSame(2, Wallet::query()->where('user_id', $user->id)->count());
    }

    public function test_ledger_rows_are_immutable(): void
    {
        $service = app(WalletService::class);
        $wallet = $service->forUser(User::factory()->create(), 'USD');
        $row = $service->credit($wallet, 100, 'x');

        $this->expectException(\LogicException::class);
        $row->update(['amount_cents' => 1_000_000]);
    }
}
