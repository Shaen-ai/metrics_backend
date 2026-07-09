<?php

namespace App\Services\LiveSearch;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CountryDetector
{
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Detect country code from IP address using ip-api.com (free, no key needed).
     * Falls back to 'AM' if detection fails.
     */
    public function detect(?string $ip): string
    {
        if (! $ip || $this->isLocalIp($ip)) {
            return $this->getDefault();
        }

        $cacheKey = "country_ip:{$ip}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::timeout(3)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,countryCode',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success' && ! empty($data['countryCode'])) {
                    // Return the raw ISO code even outside the marketplace list —
                    // callers (e.g. top-up currency) need the real country.
                    $code = strtoupper($data['countryCode']);
                    Cache::put($cacheKey, $code, self::CACHE_TTL);

                    return $code;
                }
            }
        } catch (\Throwable $e) {
            Log::debug("CountryDetector: failed for IP {$ip}", ['error' => $e->getMessage()]);
        }

        return $this->getDefault();
    }

    private function isLocalIp(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)
            || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.')
            || str_starts_with($ip, '172.');
    }

    private function getDefault(): string
    {
        return config('marketplaces.default_country', 'AM');
    }
}
