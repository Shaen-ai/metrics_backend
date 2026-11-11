<?php

namespace Tests;

use App\Models\User;
use App\Services\Tokens\TokenService;
use App\Services\Tokens\TokenTopUpService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TokenTopUpServiceTest extends TestCase
{
    private TokenTopUpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->service = new TokenTopUpService(new TokenService);
    }

    private function createTables(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->uuid('id')->primary();
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->string('name')->nullable();
                $table->string('company_name')->nullable();
                $table->string('slug')->unique();
                $table->string('user_type')->default('consumer');
                $table->string('stripe_customer_id')->nullable();
                $table->unsignedInteger('token_balance')->default(0);
                $table->string('referral_code', 32)->nullable()->unique();
                $table->uuid('referred_by_user_id')->nullable();
                $table->unsignedInteger('referral_tokens_earned')->default(0);
                $table->timestamp('first_login_bonus_granted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('token_transactions')) {
            Schema::create('token_transactions', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable()->index();
                $table->uuid('device_id')->nullable()->index();
                $table->string('type', 64);
                $table->integer('amount');
                $table->unsignedInteger('balance_after');
                $table->string('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    private function makeUser(array $overrides = []): User
    {
        $id = $overrides['id'] ?? Str::uuid()->toString();

        return User::create(array_merge([
            'id' => $id,
            'email' => "user-{$id}@example.com",
            'slug' => 'user-'.Str::lower(Str::random(8)),
            'user_type' => 'consumer',
            'token_balance' => 0,
        ], $overrides));
    }

    private function makePaidSession(string $sessionId, string $userId, int $subtotalMinor = 40000, string $currency = 'amd'): object
    {
        return (object) [
            'id' => $sessionId,
            'payment_status' => 'paid',
            'mode' => 'payment',
            'currency' => $currency,
            'amount_subtotal' => $subtotalMinor,
            'amount_total' => $subtotalMinor,
            'total_details' => null,
            'customer' => null,
            'client_reference_id' => $userId,
            'metadata' => (object) [
                'purpose' => 'token_topup',
                'user_id' => $userId,
            ],
        ];
    }

    // ── Existing unit tests ──

    public function test_tokens_from_subtotal_amd_ten_tokens(): void
    {
        $this->assertSame(10, $this->service->tokensFromSubtotal(40000, 'amd'));
    }

    public function test_tokens_from_subtotal_amd_below_minimum(): void
    {
        $this->assertSame(0, $this->service->tokensFromSubtotal(3900, 'amd'));
    }

    public function test_tokens_from_subtotal_usd_ten_tokens(): void
    {
        $this->assertSame(10, $this->service->tokensFromSubtotal(100, 'usd'));
    }

    public function test_tokens_from_subtotal_usd_below_minimum(): void
    {
        $this->assertSame(0, $this->service->tokensFromSubtotal(9, 'usd'));
    }

    public function test_tokens_from_subtotal_unknown_currency(): void
    {
        $this->assertSame(0, $this->service->tokensFromSubtotal(10000, 'eur'));
    }

    public function test_minor_units_per_token(): void
    {
        $this->assertSame(4000, $this->service->minorUnitsPerToken('amd'));
        $this->assertSame(10, $this->service->minorUnitsPerToken('usd'));
        $this->assertSame(0, $this->service->minorUnitsPerToken('eur'));
    }

    public function test_currency_for_country_amd_only_in_armenia(): void
    {
        $this->assertSame('amd', $this->service->currencyForCountry('AM'));
        $this->assertSame('amd', $this->service->currencyForCountry('am'));
        $this->assertSame('usd', $this->service->currencyForCountry('US'));
        $this->assertSame('usd', $this->service->currencyForCountry('RU'));
        $this->assertSame('usd', $this->service->currencyForCountry('DE'));
        $this->assertSame('usd', $this->service->currencyForCountry(''));
    }

    // ── creditFromCheckoutSession integration tests ──

    public function test_credit_from_checkout_session_credits_balance(): void
    {
        $user = $this->makeUser();
        $session = $this->makePaidSession('cs_test_abc123', $user->id, 40000, 'amd');

        $result = $this->service->creditFromCheckoutSession($session, $user);

        $this->assertSame(10, $result['tokensAdded']);
        $this->assertSame(10, $result['balance']);
        $this->assertFalse($result['alreadyCredited']);

        $fresh = User::find($user->id);
        $this->assertSame(10, (int) $fresh->token_balance);
    }

    public function test_credit_from_checkout_session_duplicate_is_idempotent(): void
    {
        $user = $this->makeUser();
        $session = $this->makePaidSession('cs_test_dup456', $user->id, 40000, 'amd');

        $first = $this->service->creditFromCheckoutSession($session, $user);
        $this->assertSame(10, $first['tokensAdded']);

        $second = $this->service->creditFromCheckoutSession($session, $user);
        $this->assertSame(0, $second['tokensAdded']);
        $this->assertTrue($second['alreadyCredited']);

        $fresh = User::find($user->id);
        $this->assertSame(10, (int) $fresh->token_balance);
    }

    public function test_credit_from_checkout_session_null_total_details(): void
    {
        $user = $this->makeUser();
        $session = $this->makePaidSession('cs_test_notax789', $user->id, 8000, 'amd');
        $session->total_details = null;

        $result = $this->service->creditFromCheckoutSession($session, $user);

        $this->assertSame(2, $result['tokensAdded']);
        $this->assertSame(2, $result['balance']);
    }
}
