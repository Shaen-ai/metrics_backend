<?php

namespace App\Services\Catalog;

/**
 * Room-appropriate product subtype rules (mirrors vista/src/lib/resolveCatalogSlots.ts).
 */
class RoomSubtypePolicy
{
    /** @var array<int, string> */
    private const BEDROOM_SUBTYPES = [
        'bed', 'wardrobe', 'mattress', 'crib', 'bunk_bed', 'bedroom_set',
        'duvet', 'bedding', 'pillow', 'bed_linens', 'comforter', 'mattress_topper', 'bed_sheet', 'blanket',
    ];

    /** @var array<int, string> */
    private const APPLIANCE_SUBTYPES = [
        'washing_machine', 'dishwasher', 'dryer', 'oven', 'hob',
        'hood', 'freezer', 'microwave', 'cooker', 'refrigerator',
    ];

    /** @var array<int, string> */
    private const KITCHEN_APPLIANCE_DENY = ['washing_machine', 'dryer'];

    /** @var array<int, string> */
    private const BATHROOM_APPLIANCE_DENY = [
        'refrigerator', 'oven', 'hob', 'hood', 'freezer', 'microwave', 'cooker',
    ];

    /** @var array<int, string> */
    private const APPLIANCE_ALLOWED_ROOMS = ['kitchen', 'laundry', 'bathroom'];

    /** @var array<string, array<int, string>>|null */
    private static ?array $denylist = null;

    /**
     * @return array<string, array<int, string>>
     */
    private static function denylist(): array
    {
        if (self::$denylist !== null) {
            return self::$denylist;
        }

        $livingDeny = array_values(array_unique(array_merge(self::BEDROOM_SUBTYPES, self::APPLIANCE_SUBTYPES)));

        self::$denylist = [
            'living_room' => $livingDeny,
            'living' => $livingDeny,
            'dining_room' => $livingDeny,
            'home_office' => $livingDeny,
            'hallway' => $livingDeny,
            'outdoor_patio' => $livingDeny,
            'bedroom' => array_values(array_unique(array_merge(
                ['dining_table', 'bar_stool', 'bar_table', 'kitchen_table', 'kitchen_cabinet'],
                self::APPLIANCE_SUBTYPES,
            ))),
            'kitchen' => array_values(array_unique(array_merge(
                ['bed', 'wardrobe', 'mattress', 'sofa', 'coffee_table', 'tv_stand', 'crib', 'bedroom_set', 'duvet', 'bedding'],
                self::KITCHEN_APPLIANCE_DENY,
            ))),
            'bathroom' => array_values(array_unique(array_merge(
                ['bed', 'sofa', 'wardrobe', 'dining_table', 'coffee_table', 'tv_stand', 'crib', 'bedroom_set', 'duvet', 'bedding'],
                self::BATHROOM_APPLIANCE_DENY,
            ))),
        ];

        return self::$denylist;
    }

    public static function normalizeRoomKey(string $roomType): string
    {
        $key = mb_strtolower(trim(str_replace(['-', ' '], '_', $roomType)));
        if (str_contains($key, 'living')) {
            return 'living_room';
        }
        if (str_contains($key, 'dining')) {
            return 'dining_room';
        }
        if (str_contains($key, 'office')) {
            return 'home_office';
        }
        if (str_contains($key, 'patio') || str_contains($key, 'outdoor')) {
            return 'outdoor_patio';
        }

        return $key;
    }

    public static function isSubtypeDeniedForRoom(string $roomType, ?string $subtype): bool
    {
        if ($subtype === null || trim($subtype) === '') {
            return false;
        }

        $key = self::normalizeRoomKey($roomType);
        $deny = self::denylist()[$key] ?? null;
        if ($deny === null) {
            return false;
        }

        return in_array(mb_strtolower(trim($subtype)), $deny, true);
    }

    public static function isFamilyDeniedForRoom(string $roomType, ?string $family): bool
    {
        if ($family === null || trim($family) === '') {
            return false;
        }

        if (mb_strtolower(trim($family)) !== 'home_appliances') {
            return false;
        }

        $key = self::normalizeRoomKey($roomType);

        return ! in_array($key, self::APPLIANCE_ALLOWED_ROOMS, true);
    }
}
