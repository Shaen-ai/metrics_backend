<?php

namespace Tests\Unit;

use App\Http\Middleware\SilentRegisterRateLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SilentRegisterRateLimitTest extends TestCase
{
    private SilentRegisterRateLimit $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('cache.default', 'array');
        Cache::flush();
        $this->middleware = new SilentRegisterRateLimit;
    }

    public function test_first_request_proceeds_to_next_handler(): void
    {
        $request = Request::create('/api/auth/register', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);
        $nextCalled = false;

        $response = $this->middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['ok' => true], 200);
        });

        $this->assertTrue($nextCalled);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(Cache::has('register:rate:203.0.113.10'));
    }

    public function test_second_request_within_window_returns_fake_success_without_next_handler(): void
    {
        $request = Request::create('/api/auth/register', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);
        $nextCalled = false;

        $this->middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['ok' => true], 200);
        });

        $nextCalled = false;

        $response = $this->middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['ok' => true], 200);
        });

        $this->assertFalse($nextCalled);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertSame(
            'Check your email for a link to verify your account before signing in.',
            $response->getData(true)['message'],
        );
    }

    public function test_different_ips_are_rate_limited_independently(): void
    {
        $firstIpRequest = Request::create('/api/auth/register', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);
        $secondIpRequest = Request::create('/api/auth/register', 'POST', server: ['REMOTE_ADDR' => '203.0.113.11']);
        $nextCalled = false;

        $this->middleware->handle($firstIpRequest, function () {
            return response()->json(['ok' => true], 200);
        });

        $response = $this->middleware->handle($secondIpRequest, function () use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['ok' => true], 200);
        });

        $this->assertTrue($nextCalled);
        $this->assertSame(200, $response->getStatusCode());
    }
}
