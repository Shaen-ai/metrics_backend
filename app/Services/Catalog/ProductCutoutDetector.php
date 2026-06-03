<?php

namespace App\Services\Catalog;

use App\Models\ScrapedProduct;
use Illuminate\Support\Facades\Http;

/**
 * Detect isolated product (no-background) image from scraped gallery URLs.
 */
class ProductCutoutDetector
{
    private const MIN_CONFIDENCE = 0.45;

    /**
     * @return array{url: string|null, confidence: float, scores: array<int, array{url: string, score: float}>}
     */
    public function detect(ScrapedProduct $product): array
    {
        $urls = $this->galleryUrls($product);
        if ($urls === []) {
            return ['url' => null, 'confidence' => 0.0, 'scores' => []];
        }

        $scores = [];
        foreach ($urls as $index => $url) {
            $scores[] = [
                'url' => $url,
                'score' => $this->scoreUrl($url, $index),
            ];
        }

        usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $scores[0];
        $confidence = round(min(1.0, max(0.0, $best['score'])), 3);

        if ($confidence < self::MIN_CONFIDENCE) {
            return ['url' => null, 'confidence' => $confidence, 'scores' => $scores];
        }

        return ['url' => $best['url'], 'confidence' => $confidence, 'scores' => $scores];
    }

    /**
     * @return array<int, string>
     */
    private function galleryUrls(ScrapedProduct $product): array
    {
        $seen = [];
        $out = [];

        $push = function (?string $url) use (&$seen, &$out): void {
            $u = trim((string) $url);
            if ($u === '' || ! str_starts_with($u, 'http') || isset($seen[$u])) {
                return;
            }
            $seen[$u] = true;
            $out[] = $u;
        };

        if (is_array($product->images)) {
            foreach ($product->images as $img) {
                if (is_string($img)) {
                    $push($img);
                }
            }
        }
        $push($product->main_image_url);

        return $out;
    }

    private function scoreUrl(string $url, int $index): float
    {
        $lower = mb_strtolower($url);
        $score = 0.35;

        if (preg_match('/\.png(\?|$)/i', $url)) {
            $score += 0.28;
        }
        if (preg_match('/\.webp(\?|$)/i', $url)) {
            $score += 0.12;
        }
        if (preg_match('/\.jpe?g(\?|$)/i', $url)) {
            $score -= 0.08;
        }

        foreach (['cutout', 'isolated', 'no[-_]?bg', 'nobg', 'transparent', 'product[-_]only', 'packshot', 'studio'] as $token) {
            if (preg_match('/'.$token.'/i', $lower)) {
                $score += 0.22;
                break;
            }
        }

        foreach (['room', 'lifestyle', 'interior', 'ambient', 'in[-_]situ', 'gallery[-_]room'] as $token) {
            if (preg_match('/'.$token.'/i', $lower)) {
                $score -= 0.18;
                break;
            }
        }

        if ($index === 1) {
            $score += 0.15;
        } elseif ($index === 0) {
            $score -= 0.05;
        }

        $alpha = $this->alphaRatioFromUrl($url);
        if ($alpha !== null) {
            if ($alpha >= 0.08) {
                $score += min(0.35, $alpha * 0.8);
            } else {
                $score -= 0.05;
            }
        }

        return min(1.0, max(0.0, $score));
    }

    private function alphaRatioFromUrl(string $url): ?float
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        try {
            $response = Http::timeout(8)->withHeaders(['User-Agent' => 'MebelCatalog/1.0'])->get($url);
            if (! $response->successful()) {
                return null;
            }
            $bytes = $response->body();
            if (strlen($bytes) > 900_000) {
                return null;
            }
            $img = @imagecreatefromstring($bytes);
            if ($img === false) {
                return null;
            }
            $w = imagesx($img);
            $h = imagesy($img);
            if ($w <= 0 || $h <= 0) {
                imagedestroy($img);

                return null;
            }

            $stepX = max(1, (int) floor($w / 24));
            $stepY = max(1, (int) floor($h / 24));
            $transparent = 0;
            $samples = 0;
            for ($y = 0; $y < $h; $y += $stepY) {
                for ($x = 0; $x < $w; $x += $stepX) {
                    $rgba = imagecolorat($img, $x, $y);
                    $alpha = ($rgba & 0x7F000000) >> 24;
                    if ($alpha > 0) {
                        $transparent++;
                    }
                    $samples++;
                }
            }
            imagedestroy($img);

            return $samples > 0 ? $transparent / $samples : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
