<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Tokens\TokenService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TokenServiceTest extends TestCase
{
    private TokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->service = new TokenService;
        Config::set('tokens.anonymous_grant', 20);
        Config::set('tokens.login_bonus', 0);
        Config::set('tokens.referral_invitee_bonus', 0);
        Config::set('tokens.referral_referrer_bonus', 20);
        Config::set('tokens.referral_earnings_cap', 200);
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
                $table->unsignedInteger('token_balance')->default(0);
                $table->string('referral_code', 32)->nullable()->unique();
                $table->uuid('referred_by_user_id')->nullable();
                $table->unsignedInteger('referral_tokens_earned')->default(0);
                $table->timestamp('first_login_bonus_granted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('anonymous_token_sessions')) {
            Schema::create('anonymous_token_sessions', function ($table) {
                $table->uuid('device_id')->primary();
                $table->unsignedInteger('token_balance')->default(0);
                $table->timestamp('granted_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
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

        if (! Schema::hasTable('referrals')) {
            Schema::create('referrals', function ($table) {
                $table->uuid('id')->primary();
                $table->uuid('referrer_id');
                $table->uuid('referred_user_id')->unique();
                $table->timestamp('credited_at')->nullable();
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

    public function test_grant_anonymous_if_needed_grants_configured_amount(): void
    {
        $deviceId = Str::uuid()->toString();
        $result = $this->service->grantAnonymousIfNeeded($deviceId);

        $this->assertTrue($result['granted']);
        $this->assertSame(20, $result['balance']);
    }

    public function test_grant_first_login_bonus_for_non_referred_user_grants_no_tokens(): void
    {
        $user = $this->makeUser();

        $updated = $this->service->grantFirstLoginBonus($user);

        $this->assertSame(0, (int) $updated->token_balance);
        $this->assertNotNull($updated->first_login_bonus_granted_at);
        $this->assertNotEmpty($updated->referral_code);
    }

    public function test_referred_user_gets_no_invitee_bonus_and_referrer_is_credited(): void
    {
        $referrer = $this->makeUser(['referral_code' => 'abc12345']);
        $invitee = $this->makeUser(['referred_by_user_id' => $referrer->id]);

        $updated = $this->service->grantFirstLoginBonus($invitee);

        $this->assertSame(0, (int) $updated->token_balance);
        $this->assertNotNull($updated->first_login_bonus_granted_at);

        $referrer->refresh();
        $this->assertSame(20, (int) $referrer->token_balance);
        $this->assertSame(20, (int) $referrer->referral_tokens_earned);
    }

    public function test_merge_anonymous_then_first_login_yields_twenty_not_forty(): void
    {
        $deviceId = Str::uuid()->toString();
        $this->service->grantAnonymousIfNeeded($deviceId);

        $user = $this->makeUser();
        $merged = $this->service->mergeAnonymousOnLogin($user, $deviceId);
        $updated = $this->service->grantFirstLoginBonus($merged);

        $this->assertSame(20, (int) $updated->token_balance);
    }

    public function test_first_login_bonus_is_only_granted_once(): void
    {
        $user = $this->makeUser();

        $first = $this->service->grantFirstLoginBonus($user);
        $second = $this->service->grantFirstLoginBonus($first);

        $this->assertSame(0, (int) $second->token_balance);
    }
}
