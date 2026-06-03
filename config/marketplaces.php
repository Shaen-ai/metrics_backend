<?php

use App\Services\LiveSearch\Adapters\AliexpressAdapter;
use App\Services\LiveSearch\Adapters\AmazonAdapter;
use App\Services\LiveSearch\Adapters\DigikalaAdapter;
use App\Services\LiveSearch\Adapters\DomusSearchAdapter;
use App\Services\LiveSearch\Adapters\HoffAdapter;
use App\Services\LiveSearch\Adapters\HomeCentreAdapter;
use App\Services\LiveSearch\Adapters\HomeDepotAdapter;
use App\Services\LiveSearch\Adapters\HornbachAdapter;
use App\Services\LiveSearch\Adapters\IkeaAdapter;
use App\Services\LiveSearch\Adapters\JyskSearchAdapter;
use App\Services\LiveSearch\Adapters\KoctasAdapter;
use App\Services\LiveSearch\Adapters\LeroyMerlinAdapter;
use App\Services\LiveSearch\Adapters\NoonAdapter;
use App\Services\LiveSearch\Adapters\ObiAdapter;
use App\Services\LiveSearch\Adapters\OzonAdapter;
use App\Services\LiveSearch\Adapters\SimalAdapter;
use App\Services\LiveSearch\Adapters\TrendyolAdapter;
use App\Services\LiveSearch\Adapters\VegaSearchAdapter;
use App\Services\LiveSearch\Adapters\WayfairAdapter;
use App\Services\LiveSearch\Adapters\WildberriesAdapter;

return [
    'countries' => [
        'AM' => [
            'name' => 'Armenia',
            'flag' => '🇦🇲',
            'currency' => 'AMD',
            'local' => ['vega', 'domus', 'jysk'],
            'regional' => ['wildberries_am'],
            'global' => ['aliexpress'],
        ],
        'RU' => [
            'name' => 'Russia',
            'flag' => '🇷🇺',
            'currency' => 'RUB',
            'local' => ['wildberries_ru', 'ozon'],
            'regional' => ['leroy_merlin_ru', 'hoff'],
            'global' => ['ikea_ru', 'aliexpress'],
        ],
        'TR' => [
            'name' => 'Turkey',
            'flag' => '🇹🇷',
            'currency' => 'TRY',
            'local' => ['trendyol', 'koctas'],
            'regional' => ['ikea_tr'],
            'global' => ['amazon_tr', 'aliexpress'],
        ],
        'GE' => [
            'name' => 'Georgia',
            'flag' => '🇬🇪',
            'currency' => 'GEL',
            'local' => ['simal_ge'],
            'regional' => ['wildberries_ge'],
            'global' => ['aliexpress'],
        ],
        'AE' => [
            'name' => 'UAE',
            'flag' => '🇦🇪',
            'currency' => 'AED',
            'local' => ['noon', 'home_centre'],
            'regional' => ['ikea_ae', 'amazon_ae'],
            'global' => ['wayfair'],
        ],
        'IR' => [
            'name' => 'Iran',
            'flag' => '🇮🇷',
            'currency' => 'IRR',
            'local' => ['digikala'],
            'regional' => [],
            'global' => ['aliexpress'],
        ],
        'US' => [
            'name' => 'United States',
            'flag' => '🇺🇸',
            'currency' => 'USD',
            'local' => ['wayfair', 'home_depot'],
            'regional' => ['ikea_us'],
            'global' => ['amazon_us'],
        ],
        'GB' => [
            'name' => 'United Kingdom',
            'flag' => '🇬🇧',
            'currency' => 'GBP',
            'local' => ['ikea_gb', 'wayfair_gb'],
            'regional' => [],
            'global' => ['amazon_gb'],
        ],
        'DE' => [
            'name' => 'Germany',
            'flag' => '🇩🇪',
            'currency' => 'EUR',
            'local' => ['ikea_de', 'obi_de', 'hornbach'],
            'regional' => [],
            'global' => ['amazon_de'],
        ],
        'FR' => [
            'name' => 'France',
            'flag' => '🇫🇷',
            'currency' => 'EUR',
            'local' => ['leroy_merlin_fr', 'ikea_fr'],
            'regional' => [],
            'global' => ['amazon_fr'],
        ],
    ],

    'adapters' => [
        'vega' => [
            'class' => VegaSearchAdapter::class,
            'base_url' => 'https://vega.am',
            'name' => 'Vega',
            'logo' => '/images/marketplaces/vega.png',
        ],
        'domus' => [
            'class' => DomusSearchAdapter::class,
            'base_url' => 'https://domus.am',
            'name' => 'Domus',
            'logo' => '/images/marketplaces/domus.png',
        ],
        'jysk' => [
            'class' => JyskSearchAdapter::class,
            'base_url' => 'https://jysk.am',
            'name' => 'JYSK',
            'logo' => '/images/marketplaces/jysk.png',
        ],
        'wildberries_am' => [
            'class' => WildberriesAdapter::class,
            'base_url' => 'https://www.wildberries.am',
            'search_host' => 'search.wb.ru',
            'locale' => 'am',
            'dest' => -5551776,
            'name' => 'Wildberries',
            'logo' => '/images/marketplaces/wildberries.png',
        ],
        'wildberries_ru' => [
            'class' => WildberriesAdapter::class,
            'base_url' => 'https://www.wildberries.ru',
            'search_host' => 'search.wb.ru',
            'locale' => 'ru',
            'dest' => -1257786,
            'name' => 'Wildberries',
            'logo' => '/images/marketplaces/wildberries.png',
        ],
        'wildberries_ge' => [
            'class' => WildberriesAdapter::class,
            'base_url' => 'https://www.wildberries.ge',
            'search_host' => 'search.wb.ru',
            'locale' => 'ge',
            'dest' => -2133462,
            'name' => 'Wildberries',
            'logo' => '/images/marketplaces/wildberries.png',
        ],
        'ozon' => [
            'class' => OzonAdapter::class,
            'base_url' => 'https://www.ozon.ru',
            'name' => 'Ozon',
            'logo' => '/images/marketplaces/ozon.png',
        ],
        'trendyol' => [
            'class' => TrendyolAdapter::class,
            'base_url' => 'https://www.trendyol.com',
            'name' => 'Trendyol',
            'logo' => '/images/marketplaces/trendyol.png',
        ],
        'koctas' => [
            'class' => KoctasAdapter::class,
            'base_url' => 'https://www.koctas.com.tr',
            'name' => 'Koçtaş',
            'logo' => '/images/marketplaces/koctas.png',
        ],
        'digikala' => [
            'class' => DigikalaAdapter::class,
            'base_url' => 'https://www.digikala.com',
            'name' => 'Digikala',
            'logo' => '/images/marketplaces/digikala.png',
        ],
        'ikea_ru' => [
            'class' => IkeaAdapter::class,
            'base_url' => 'https://www.ikea.com/ru/ru',
            'country_code' => 'ru',
            'language_code' => 'ru',
            'name' => 'IKEA',
            'logo' => '/images/marketplaces/ikea.png',
        ],
        'ikea_tr' => [
            'class' => IkeaAdapter::class,
            'base_url' => 'https://www.ikea.com.tr',
            'country_code' => 'tr',
            'language_code' => 'tr',
            'name' => 'IKEA',
            'logo' => '/images/marketplaces/ikea.png',
        ],
        'ikea_ae' => [
            'class' => IkeaAdapter::class,
            'base_url' => 'https://www.ikea.com/ae/en',
            'country_code' => 'ae',
            'language_code' => 'en',
            'name' => 'IKEA',
            'logo' => '/images/marketplaces/ikea.png',
        ],
        'ikea_us' => [
            'class' => IkeaAdapter::class,
            'base_url' => 'https://www.ikea.com/us/en',
            'country_code' => 'us',
            'language_code' => 'en',
            'name' => 'IKEA',
            'logo' => '/images/marketplaces/ikea.png',
        ],
        'ikea_gb' => [
            'class' => IkeaAdapter::class,
            'base_url' => 'https://www.ikea.com/gb/en',
            'country_code' => 'gb',
            'language_code' => 'en',
            'name' => 'IKEA',
            'logo' => '/images/marketplaces/ikea.png',
        ],
        'ikea_de' => [
            'class' => IkeaAdapter::class,
            'base_url' => 'https://www.ikea.com/de/de',
            'country_code' => 'de',
            'language_code' => 'de',
            'name' => 'IKEA',
            'logo' => '/images/marketplaces/ikea.png',
        ],
        'ikea_fr' => [
            'class' => IkeaAdapter::class,
            'base_url' => 'https://www.ikea.com/fr/fr',
            'country_code' => 'fr',
            'language_code' => 'fr',
            'name' => 'IKEA',
            'logo' => '/images/marketplaces/ikea.png',
        ],
        'aliexpress' => [
            'class' => AliexpressAdapter::class,
            'base_url' => 'https://www.aliexpress.com',
            'name' => 'AliExpress',
            'logo' => '/images/marketplaces/aliexpress.png',
        ],
        'amazon_us' => [
            'class' => AmazonAdapter::class,
            'base_url' => 'https://www.amazon.com',
            'name' => 'Amazon',
            'logo' => '/images/marketplaces/amazon.png',
        ],
        'amazon_ae' => [
            'class' => AmazonAdapter::class,
            'base_url' => 'https://www.amazon.ae',
            'name' => 'Amazon',
            'logo' => '/images/marketplaces/amazon.png',
        ],
        'amazon_tr' => [
            'class' => AmazonAdapter::class,
            'base_url' => 'https://www.amazon.com.tr',
            'name' => 'Amazon',
            'logo' => '/images/marketplaces/amazon.png',
        ],
        'amazon_gb' => [
            'class' => AmazonAdapter::class,
            'base_url' => 'https://www.amazon.co.uk',
            'name' => 'Amazon',
            'logo' => '/images/marketplaces/amazon.png',
        ],
        'amazon_de' => [
            'class' => AmazonAdapter::class,
            'base_url' => 'https://www.amazon.de',
            'name' => 'Amazon',
            'logo' => '/images/marketplaces/amazon.png',
        ],
        'amazon_fr' => [
            'class' => AmazonAdapter::class,
            'base_url' => 'https://www.amazon.fr',
            'name' => 'Amazon',
            'logo' => '/images/marketplaces/amazon.png',
        ],
        'noon' => [
            'class' => NoonAdapter::class,
            'base_url' => 'https://www.noon.com',
            'name' => 'Noon',
            'logo' => '/images/marketplaces/noon.png',
        ],
        'wayfair' => [
            'class' => WayfairAdapter::class,
            'base_url' => 'https://www.wayfair.com',
            'name' => 'Wayfair',
            'logo' => '/images/marketplaces/wayfair.png',
        ],
        'wayfair_gb' => [
            'class' => WayfairAdapter::class,
            'base_url' => 'https://www.wayfair.co.uk',
            'name' => 'Wayfair',
            'logo' => '/images/marketplaces/wayfair.png',
        ],
        'home_depot' => [
            'class' => HomeDepotAdapter::class,
            'base_url' => 'https://www.homedepot.com',
            'name' => 'Home Depot',
            'logo' => '/images/marketplaces/homedepot.png',
        ],
        'leroy_merlin_ru' => [
            'class' => LeroyMerlinAdapter::class,
            'base_url' => 'https://leroymerlin.ru',
            'name' => 'Leroy Merlin',
            'logo' => '/images/marketplaces/leroymerlin.png',
        ],
        'leroy_merlin_fr' => [
            'class' => LeroyMerlinAdapter::class,
            'base_url' => 'https://www.leroymerlin.fr',
            'name' => 'Leroy Merlin',
            'logo' => '/images/marketplaces/leroymerlin.png',
        ],
        'hoff' => [
            'class' => HoffAdapter::class,
            'base_url' => 'https://hoff.ru',
            'name' => 'Hoff',
            'logo' => '/images/marketplaces/hoff.png',
        ],
        'simal_ge' => [
            'class' => SimalAdapter::class,
            'base_url' => 'https://simal.ge',
            'name' => 'Simal',
            'logo' => '/images/marketplaces/simal.png',
        ],
        'home_centre' => [
            'class' => HomeCentreAdapter::class,
            'base_url' => 'https://www.homecentre.com',
            'name' => 'Home Centre',
            'logo' => '/images/marketplaces/homecentre.png',
        ],
        'obi_de' => [
            'class' => ObiAdapter::class,
            'base_url' => 'https://www.obi.de',
            'name' => 'OBI',
            'logo' => '/images/marketplaces/obi.png',
        ],
        'hornbach' => [
            'class' => HornbachAdapter::class,
            'base_url' => 'https://www.hornbach.de',
            'name' => 'Hornbach',
            'logo' => '/images/marketplaces/hornbach.png',
        ],
    ],

    'default_country' => 'AM',

    'defaults' => [
        'timeout' => 8,
        'cache_ttl' => 300,
        'max_results_per_adapter' => 20,
    ],
];
