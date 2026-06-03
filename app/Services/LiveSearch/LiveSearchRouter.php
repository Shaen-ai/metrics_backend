<?php

namespace App\Services\LiveSearch;

use App\Services\LiveSearch\Adapters\SearchAdapterInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiveSearchRouter
{
    /**
     * Execute a live search across all marketplaces for the given country + mode.
     *
     * @return array{results: array, sources: array, meta: array}
     */
    public function search(string $query, string $countryCode, string $mode = 'local'): array
    {
        $countryCode = strtoupper($countryCode);
        $countries = config('marketplaces.countries');
        $adaptersConfig = config('marketplaces.adapters');
        $defaults = config('marketplaces.defaults');

        if (! isset($countries[$countryCode])) {
            return [
                'results' => [],
                'sources' => [],
                'meta' => ['error' => "Country '{$countryCode}' not supported", 'country' => $countryCode, 'mode' => $mode],
            ];
        }

        $country = $countries[$countryCode];
        $adapterKeys = $country[$mode] ?? [];

        if (empty($adapterKeys)) {
            return [
                'results' => [],
                'sources' => [],
                'meta' => ['country' => $countryCode, 'mode' => $mode, 'message' => "No marketplaces configured for {$mode} mode in {$country['name']}"],
            ];
        }

        $cacheKey = "live_search:{$countryCode}:{$mode}:".md5($query);
        $cacheTtl = $defaults['cache_ttl'] ?? 300;

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $allResults = collect();
        $sources = [];
        $maxPerAdapter = $defaults['max_results_per_adapter'] ?? 20;

        $adapterInstances = [];
        foreach ($adapterKeys as $key) {
            if (! isset($adaptersConfig[$key])) {
                Log::warning("LiveSearch: adapter config missing for key '{$key}'");

                continue;
            }

            $cfg = $adaptersConfig[$key];
            $class = $cfg['class'] ?? null;

            if (! $class || ! class_exists($class)) {
                Log::warning("LiveSearch: adapter class missing or not found for key '{$key}'", ['class' => $class]);

                continue;
            }

            $adapter = app($class);
            if (! $adapter instanceof SearchAdapterInterface) {
                Log::warning("LiveSearch: '{$class}' does not implement SearchAdapterInterface");

                continue;
            }

            $adapterInstances[$key] = ['adapter' => $adapter, 'config' => array_merge($cfg, ['key' => $key])];
        }

        // Fire concurrent requests using Laravel's Http::pool where possible,
        // but since adapters handle their own HTTP calls, we run them sequentially
        // with individual timeouts. For truly concurrent execution, dispatch as jobs
        // or use a process pool — but for <5 adapters, sequential with 8s timeout is fine.
        foreach ($adapterInstances as $key => $entry) {
            /** @var SearchAdapterInterface $adapter */
            $adapter = $entry['adapter'];
            $cfg = $entry['config'];

            $startTime = microtime(true);

            try {
                $results = $adapter->search($query, $cfg);
                $elapsed = round((microtime(true) - $startTime) * 1000);

                $results = $results->take($maxPerAdapter);
                $allResults = $allResults->merge($results);

                $sources[] = [
                    'key' => $key,
                    'name' => $cfg['name'] ?? $adapter->getMarketplaceName(),
                    'logo' => $cfg['logo'] ?? null,
                    'count' => $results->count(),
                    'elapsed_ms' => $elapsed,
                    'status' => 'ok',
                ];
            } catch (\Throwable $e) {
                $elapsed = round((microtime(true) - $startTime) * 1000);
                Log::warning("LiveSearch: adapter '{$key}' failed", [
                    'error' => $e->getMessage(),
                    'elapsed_ms' => $elapsed,
                ]);

                $sources[] = [
                    'key' => $key,
                    'name' => $cfg['name'] ?? $adapter->getMarketplaceName(),
                    'logo' => $cfg['logo'] ?? null,
                    'count' => 0,
                    'elapsed_ms' => $elapsed,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $response = [
            'results' => $allResults->map(fn (LiveSearchResult $r) => $r->toArray())->values()->all(),
            'sources' => $sources,
            'meta' => [
                'country' => $countryCode,
                'country_name' => $country['name'],
                'mode' => $mode,
                'total' => $allResults->count(),
                'query' => $query,
            ],
        ];

        Cache::put($cacheKey, $response, $cacheTtl);

        return $response;
    }

    /**
     * Return the list of supported countries with their available modes.
     */
    public function supportedCountries(): array
    {
        $countries = config('marketplaces.countries');
        $result = [];

        foreach ($countries as $code => $data) {
            $modes = [];
            if (! empty($data['local'])) {
                $modes[] = 'local';
            }
            if (! empty($data['regional'])) {
                $modes[] = 'regional';
            }
            if (! empty($data['global'])) {
                $modes[] = 'global';
            }

            $result[] = [
                'code' => $code,
                'name' => $data['name'],
                'flag' => $data['flag'] ?? null,
                'currency' => $data['currency'] ?? null,
                'modes' => $modes,
            ];
        }

        return $result;
    }
}
