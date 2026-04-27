<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ImageProxyController extends Controller
{
    /** @var list<string> Hosts (or root domains) allowed for `?url=`; subdomains match via suffix. */
    private const ALLOWED_HOSTS = [
        'cdn.egger.com',
        'www.egger.com',
        'egger.com',
        'alvic.com',
        'agtwood.com',
        'kastamonu.com',
        'kastamonu.com.tr',
        'images.unsplash.com',
        'marco.am',
    ];

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    public function __invoke(Request $request): Response
    {
        $request->validate([
            'url' => ['required', 'url'],
        ]);

        $url = $request->input('url');
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host || ! $this->isAllowedHost($host)) {
            return response()->json(['message' => 'Host not allowed.'], 403);
        }

        $cacheKey = 'img_proxy:' . md5($url);

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response($cached['body'])
                ->header('Content-Type', $cached['content_type'])
                ->header('Cache-Control', 'public, max-age=86400')
                ->header('Access-Control-Allow-Origin', '*');
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Accept' => 'image/*'])
                ->get($url);

            if (! $response->successful()) {
                return response()->json(
                    ['message' => 'Upstream returned ' . $response->status()],
                    502,
                );
            }

            $contentType = $response->header('Content-Type') ?? 'image/png';

            if (! str_starts_with($contentType, 'image/')) {
                return response()->json(['message' => 'Not an image.'], 422);
            }

            $body = $response->body();

            Cache::put($cacheKey, [
                'body' => $body,
                'content_type' => $contentType,
            ], self::CACHE_TTL);

            return response($body)
                ->header('Content-Type', $contentType)
                ->header('Cache-Control', 'public, max-age=86400')
                ->header('Access-Control-Allow-Origin', '*');
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Failed to fetch image: ' . $e->getMessage()],
                502,
            );
        }
    }

    private function isAllowedHost(string $host): bool
    {
        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }
}
