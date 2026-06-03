<?php

namespace App\Http\Controllers;

use App\Jobs\DiscoverSearchUrlsJob;
use App\Services\LiveSearch\CountryDetector;
use App\Services\LiveSearch\LiveSearchRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LiveSearchController extends Controller
{
    public function __construct(
        private readonly LiveSearchRouter $router
    ) {}

    /**
     * POST /api/marketplace/live-search
     *
     * Live-searches marketplaces based on user's country and mode.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:200',
            'country' => 'required|string|size:2',
            'mode' => 'nullable|string|in:local,regional,global',
        ]);

        $query = $request->input('q');
        $country = strtoupper($request->input('country'));
        $mode = $request->input('mode', 'local');

        $result = $this->router->search($query, $country, $mode);

        if ($country === 'AM' && $mode === 'local') {
            $trimmed = trim((string) $query);
            if ($trimmed !== '') {
                $normalized = mb_strtolower($trimmed);
                $ttl = (int) config('scraper.search_discovery.throttle_seconds', 3600);

                foreach (['vega', 'domus', 'jysk'] as $marketplace) {
                    $cacheKey = 'search_discover:'.$marketplace.':'.md5($normalized);
                    if (Cache::add($cacheKey, true, $ttl)) {
                        DiscoverSearchUrlsJob::dispatch($trimmed, $marketplace)->afterResponse();
                    }
                }
            }
        }

        return response()->json($result);
    }

    /**
     * GET /api/marketplace/countries
     *
     * Returns the list of supported countries and their available modes.
     */
    public function countries(): JsonResponse
    {
        $countries = $this->router->supportedCountries();

        return response()->json(['data' => $countries]);
    }

    /**
     * GET /api/marketplace/detect-country
     *
     * Auto-detect country from request IP.
     */
    public function detectCountry(Request $request): JsonResponse
    {
        $detector = app(CountryDetector::class);
        $ip = $request->ip();
        $country = $detector->detect($ip);

        return response()->json([
            'data' => [
                'country_code' => $country,
                'ip' => $ip,
            ],
        ]);
    }
}
