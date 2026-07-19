<?php

namespace Tests\Unit;

use App\Services\LiveSearch\CountryDetector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\CreatesApplication;
use Tests\TestCase;

class CountryDetectorTest extends TestCase
{
    use CreatesApplication;

    private CountryDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('cache.default', 'array');
        Cache::flush();
        $this->detector = new CountryDetector;
    }

    public function test_local_ip_returns_fallback_without_detection(): void
    {
        $result = $this->detector->detectWithMeta('127.0.0.1');

        $this->assertSame('AM', $result['country']);
        $this->assertFalse($result['detected']);
    }

    public function test_private_172_16_range_returns_fallback_without_detection(): void
    {
        $result = $this->detector->detectWithMeta('172.16.0.1');

        $this->assertSame('AM', $result['country']);
        $this->assertFalse($result['detected']);
    }

    public function test_public_172_outside_private_range_is_not_treated_as_local(): void
    {
        Http::fake([
            'http://ip-api.com/json/172.217.0.1*' => Http::response([
                'status' => 'success',
                'countryCode' => 'US',
            ]),
        ]);

        $result = $this->detector->detectWithMeta('172.217.0.1');

        $this->assertSame('US', $result['country']);
        $this->assertTrue($result['detected']);
    }

    public function test_successful_ip_lookup_returns_detected_country(): void
    {
        Http::fake([
            'http://ip-api.com/json/8.8.8.8*' => Http::response([
                'status' => 'success',
                'countryCode' => 'US',
            ]),
        ]);

        $result = $this->detector->detectWithMeta('8.8.8.8');

        $this->assertSame('US', $result['country']);
        $this->assertTrue($result['detected']);
    }

    public function test_failed_ip_lookup_returns_fallback_without_detection(): void
    {
        Http::fake([
            'http://ip-api.com/json/8.8.4.4*' => Http::response([
                'status' => 'fail',
            ]),
        ]);

        $result = $this->detector->detectWithMeta('8.8.4.4');

        $this->assertSame('AM', $result['country']);
        $this->assertFalse($result['detected']);
    }

    public function test_cached_country_is_treated_as_detected(): void
    {
        Cache::put('country_ip:1.2.3.4', 'IN', 86400);

        Http::fake();

        $result = $this->detector->detectWithMeta('1.2.3.4');

        $this->assertSame('IN', $result['country']);
        $this->assertTrue($result['detected']);
        Http::assertNothingSent();
    }

    public function test_detect_returns_country_string_for_legacy_callers(): void
    {
        Http::fake([
            'http://ip-api.com/json/8.8.8.8*' => Http::response([
                'status' => 'success',
                'countryCode' => 'US',
            ]),
        ]);

        $this->assertSame('US', $this->detector->detect('8.8.8.8'));
    }
}
