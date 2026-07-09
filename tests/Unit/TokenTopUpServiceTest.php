<?php

namespace Tests;

use App\Services\Tokens\TokenService;
use App\Services\Tokens\TokenTopUpService;

class TokenTopUpServiceTest extends TestCase
{
    private TokenTopUpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TokenTopUpService(new TokenService);
    }

    public function test_tokens_from_subtotal_amd_ten_tokens(): void
    {
        // 400 AMD = 40000 minor units → 10 tokens at 40 AMD each.
        $this->assertSame(10, $this->service->tokensFromSubtotal(40000, 'amd'));
    }

    public function test_tokens_from_subtotal_amd_below_minimum(): void
    {
        // 39 AMD = 3900 minor units → below one token.
        $this->assertSame(0, $this->service->tokensFromSubtotal(3900, 'amd'));
    }

    public function test_tokens_from_subtotal_usd_ten_tokens(): void
    {
        // $1.00 = 100 cents → 10 tokens at $0.10 each.
        $this->assertSame(10, $this->service->tokensFromSubtotal(100, 'usd'));
    }

    public function test_tokens_from_subtotal_usd_below_minimum(): void
    {
        // $0.09 = 9 cents → below one token.
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
}
