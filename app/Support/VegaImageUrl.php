<?php

namespace App\Support;

class VegaImageUrl
{
    /**
     * Normalize a Vega.am image URL from OpenCart cache form to canonical source.
     *
     * Cache URLs like `.../image/cache/catalog/…/file-2000x1500.jpg` 404 when
     * Vega regenerates its cache with different dimensions. The canonical source
     * at `.../image/catalog/…/file.jpg` is stable and always serves HTTP 200.
     */
    public static function normalize(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || ! self::isVegaHost($host)) {
            return $url;
        }

        $normalized = str_replace('/image/cache/catalog/', '/image/catalog/', $url);

        $normalized = preg_replace('/-\d+x\d+\.(jpe?g|png|webp|gif)$/i', '.$1', $normalized);

        return $normalized;
    }

    /**
     * Normalize every URL in a JSON-decoded images array.
     *
     * @param  list<string>|null  $images
     * @return list<string>|null
     */
    public static function normalizeArray(?array $images): ?array
    {
        if ($images === null) {
            return null;
        }

        $seen = [];
        $out = [];

        foreach ($images as $url) {
            if (! is_string($url)) {
                continue;
            }
            $fixed = self::normalize($url) ?? $url;
            if (! isset($seen[$fixed])) {
                $seen[$fixed] = true;
                $out[] = $fixed;
            }
        }

        return $out;
    }

    private static function isVegaHost(string $host): bool
    {
        return $host === 'vega.am' || str_ends_with($host, '.vega.am');
    }
}
