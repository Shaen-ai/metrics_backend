<?php

namespace App\Services;

class ColorDetectorService
{
    /**
     * Detect the dominant color from an image URL.
     *
     * @return array{hex: string, name: string, rgb: array{r: int, g: int, b: int}}
     */
    public function detect(string $imageUrl): array
    {
        $imageData = @file_get_contents($imageUrl);

        if ($imageData === false) {
            throw new \RuntimeException('Could not download image from the provided URL.');
        }

        $image = @imagecreatefromstring($imageData);

        if ($image === false) {
            throw new \RuntimeException('Could not parse the downloaded file as an image.');
        }

        $rgb = $this->extractDominantColor($image);
        imagedestroy($image);

        $hex = sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
        $name = $this->closestColorName($rgb['r'], $rgb['g'], $rgb['b']);

        return [
            'rgb' => $rgb,
            'hex' => $hex,
            'name' => $name,
        ];
    }

    /**
     * Resize the image to a small grid and compute the average color,
     * ignoring very bright (white background) and very dark pixels.
     */
    private function extractDominantColor(\GdImage $image): array
    {
        $sampleSize = 10;
        $thumb = imagecreatetruecolor($sampleSize, $sampleSize);
        imagecopyresampled(
            $thumb, $image,
            0, 0, 0, 0,
            $sampleSize, $sampleSize,
            imagesx($image), imagesy($image),
        );

        $rTotal = 0;
        $gTotal = 0;
        $bTotal = 0;
        $count = 0;

        for ($x = 0; $x < $sampleSize; $x++) {
            for ($y = 0; $y < $sampleSize; $y++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $brightness = ($r + $g + $b) / 3;
                if ($brightness < 250 && $brightness > 5) {
                    $rTotal += $r;
                    $gTotal += $g;
                    $bTotal += $b;
                    $count++;
                }
            }
        }

        imagedestroy($thumb);

        if ($count === 0) {
            return ['r' => 255, 'g' => 255, 'b' => 255];
        }

        return [
            'r' => (int) round($rTotal / $count),
            'g' => (int) round($gTotal / $count),
            'b' => (int) round($bTotal / $count),
        ];
    }

    /**
     * Find the closest named color using Euclidean distance in RGB space.
     */
    private function closestColorName(int $r, int $g, int $b): string
    {
        $palette = self::palette();

        $bestName = 'Unknown';
        $bestDist = PHP_INT_MAX;

        foreach ($palette as $name => [$pr, $pg, $pb]) {
            $dist = ($r - $pr) ** 2 + ($g - $pg) ** 2 + ($b - $pb) ** 2;
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $bestName = $name;
            }
        }

        return $bestName;
    }

    /**
     * Curated color palette with names commonly used for materials & laminates.
     *
     * @return array<string, array{0: int, 1: int, 2: int}>
     */
    private static function palette(): array
    {
        return [
            // Whites & Off-whites
            'White'             => [255, 255, 255],
            'Snow White'        => [255, 250, 250],
            'Ivory'             => [255, 255, 240],
            'Cream'             => [255, 253, 208],
            'Linen'             => [250, 240, 230],
            'Antique White'     => [250, 235, 215],
            'Pearl'             => [234, 224, 200],
            'Alabaster'         => [242, 240, 230],

            // Grays
            'Light Gray'        => [211, 211, 211],
            'Silver'            => [192, 192, 192],
            'Gray'              => [128, 128, 128],
            'Charcoal'          => [54, 69, 79],
            'Slate Gray'        => [112, 128, 144],
            'Ash Gray'          => [178, 190, 181],
            'Dove Gray'         => [169, 169, 169],
            'Graphite'          => [56, 56, 56],

            // Blacks
            'Black'             => [0, 0, 0],
            'Jet Black'         => [13, 13, 13],
            'Onyx'              => [53, 56, 57],

            // Browns & Wood tones
            'Light Oak'         => [210, 176, 131],
            'Natural Oak'       => [195, 161, 113],
            'Golden Oak'        => [185, 145, 75],
            'Honey Oak'         => [194, 154, 89],
            'Medium Oak'        => [170, 130, 80],
            'Dark Oak'          => [120, 81, 45],
            'Walnut'            => [94, 63, 42],
            'Dark Walnut'       => [72, 47, 29],
            'Mahogany'          => [103, 36, 34],
            'Cherry'            => [137, 49, 42],
            'Light Cherry'      => [175, 89, 62],
            'Maple'             => [210, 176, 131],
            'Birch'             => [222, 203, 163],
            'Pine'              => [218, 194, 130],
            'Teak'              => [181, 137, 77],
            'Cedar'             => [160, 102, 57],
            'Rosewood'          => [101, 0, 11],
            'Chestnut'          => [149, 69, 53],
            'Espresso'          => [59, 31, 16],
            'Wenge'             => [64, 48, 34],
            'Driftwood'         => [175, 155, 120],
            'Sandalwood'        => [171, 134, 98],
            'Caramel'           => [175, 137, 72],
            'Toffee'            => [143, 98, 52],
            'Mocha'             => [108, 77, 57],
            'Cocoa'             => [87, 59, 40],
            'Tan'               => [210, 180, 140],
            'Khaki'             => [195, 176, 145],
            'Sand'              => [194, 178, 128],
            'Beige'             => [245, 245, 220],
            'Taupe'             => [163, 148, 128],
            'Sienna'            => [160, 82, 45],
            'Brown'             => [139, 90, 43],
            'Dark Brown'        => [92, 64, 51],
            'Chocolate'         => [86, 47, 14],
            'Tobacco'           => [113, 85, 48],

            // Reds
            'Red'               => [205, 38, 38],
            'Burgundy'          => [128, 0, 32],
            'Wine Red'          => [114, 47, 55],
            'Crimson'           => [176, 23, 31],
            'Brick Red'         => [165, 72, 68],
            'Terracotta'        => [204, 119, 75],
            'Coral'             => [255, 127, 80],
            'Rust'              => [183, 65, 14],

            // Oranges
            'Orange'            => [255, 165, 0],
            'Peach'             => [255, 218, 185],
            'Apricot'           => [251, 206, 177],
            'Amber'             => [255, 191, 0],
            'Copper'            => [184, 115, 51],

            // Yellows
            'Yellow'            => [255, 223, 0],
            'Light Yellow'      => [255, 255, 204],
            'Gold'              => [212, 175, 55],
            'Mustard'           => [207, 181, 59],
            'Butter'            => [255, 241, 161],
            'Champagne'         => [247, 231, 206],

            // Greens
            'Green'             => [0, 128, 0],
            'Forest Green'      => [34, 79, 34],
            'Olive'             => [128, 128, 0],
            'Sage'              => [188, 184, 138],
            'Mint'              => [189, 252, 201],
            'Emerald'           => [0, 135, 68],
            'Moss Green'        => [138, 154, 91],
            'Hunter Green'      => [53, 94, 59],
            'Lime'              => [166, 214, 8],
            'Seafoam'           => [147, 224, 186],

            // Blues
            'Blue'              => [0, 0, 205],
            'Navy'              => [0, 0, 128],
            'Royal Blue'        => [65, 105, 225],
            'Sky Blue'          => [135, 206, 235],
            'Baby Blue'         => [176, 218, 255],
            'Steel Blue'        => [70, 130, 180],
            'Teal'              => [0, 128, 128],
            'Cyan'              => [0, 183, 235],
            'Powder Blue'       => [176, 224, 230],
            'Denim'             => [111, 143, 175],
            'Indigo'            => [63, 0, 127],

            // Purples
            'Purple'            => [128, 0, 128],
            'Plum'              => [142, 69, 133],
            'Lavender'          => [230, 230, 250],
            'Mauve'             => [176, 134, 171],
            'Violet'            => [127, 0, 255],
            'Eggplant'          => [97, 64, 81],

            // Pinks
            'Pink'              => [255, 182, 193],
            'Rose'              => [188, 91, 114],
            'Blush'             => [222, 164, 164],
            'Salmon'            => [250, 128, 114],
            'Dusty Rose'        => [194, 133, 133],
        ];
    }
}
