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
        return $this->detectWithMeta($ip)['country'];
    }

    /**
     * @return array{country: string, detected: bool}
     */
    public function detectWithMeta(?string $ip): array
    {
        if (! $ip || $this->isLocalIp($ip)) {
            return [
                'country' => $this->getDefault(),
                'detected' => false,
            ];
        }

        $cacheKey = "country_ip:{$ip}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return [
                'country' => $cached,
                'detected' => true,
            ];
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

                    return [
                        'country' => $code,
                        'detected' => true,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::debug("CountryDetector: failed for IP {$ip}", ['error' => $e->getMessage()]);
        }

        return [
            'country' => $this->getDefault(),
            'detected' => false,
        ];
    }

    private function isLocalIp(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
            return true;
        }

        if (str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long !== false) {
                $start = ip2long('172.16.0.0');
                $end = ip2long('172.31.255.255');
                if ($long >= $start && $long <= $end) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getDefault(): string
    {
        return config('marketplaces.default_country', 'AM');
    }
}
