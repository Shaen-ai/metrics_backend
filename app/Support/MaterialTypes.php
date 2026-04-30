<?php

namespace App\Support;

/**
 * Canonical ordering for material `type` slugs (first = legacy `type` column).
 */
class MaterialTypes
{
    /** @var list<string> */
    /** Admin + API primary slugs; tail entries sort legacy DB / imports only. */
    public const ORDER = [
        'laminate', 'mdf', 'wood', 'worktop', 'slide', 'hinge', 'handle',
        'metal', 'fabric', 'boucle', 'glass', 'plastic', 'leather', 'stone',
    ];

    /**
     * @param  list<string>|null  $types
     * @return list<string> non-empty unique slugs, sorted by ORDER then name
     */
    public static function normalize(?array $types, ?string $legacyType): array
    {
        $list = [];
        if (is_array($types) && count($types) > 0) {
            foreach ($types as $t) {
                if ($t === null || $t === '') {
                    continue;
                }
                $list[] = (string) $t;
            }
        } elseif ($legacyType !== null && $legacyType !== '') {
            $list = [(string) $legacyType];
        }

        $list = array_values(array_unique($list));
        usort($list, function (string $a, string $b): int {
            $ia = array_search($a, self::ORDER, true);
            $ib = array_search($b, self::ORDER, true);
            $ia = $ia === false ? 999 : $ia;
            $ib = $ib === false ? 999 : $ib;
            if ($ia !== $ib) {
                return $ia <=> $ib;
            }

            return strcmp($a, $b);
        });

        return $list;
    }
}
