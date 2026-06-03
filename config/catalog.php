<?php

return [

    'embedding' => [
        'provider' => env('CATALOG_EMBEDDING_PROVIDER', 'openai'),
        'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('CATALOG_EMBEDDING_DIMENSIONS', 1536),
        'text_version' => env('CATALOG_EMBEDDING_TEXT_VERSION', 'v1'),
        'description_max_chars' => (int) env('CATALOG_EMBEDDING_DESCRIPTION_MAX', 400),
        'openai_api_key' => env('OPENAI_API_KEY'),
    ],

    'qdrant' => [
        'url' => rtrim((string) env('QDRANT_URL', 'http://127.0.0.1:6333'), '/'),
        'api_key' => env('QDRANT_API_KEY'),
        'collection' => env('QDRANT_CATALOG_COLLECTION', 'catalog_products_v1'),
        'multimodal_collection' => env('QDRANT_MULTIMODAL_COLLECTION', 'catalog_products_image_v1'),
        'recall_limit' => (int) env('CATALOG_QDRANT_RECALL_LIMIT', 20),
        'recall_limit_broad' => (int) env('CATALOG_QDRANT_RECALL_LIMIT_BROAD', 50),
    ],

    'catalog_items_qdrant' => [
        'collection' => env('QDRANT_CATALOG_ITEMS_COLLECTION', 'catalog_items_v1'),
    ],

    'enrichment' => [
        'model' => env('CATALOG_GEMINI_ENRICH_MODEL', 'gemini-2.5-flash-lite'),
        'google_api_key' => env('GOOGLE_AI_API_KEY', env('GEMINI_API_KEY')),
    ],

    'multimodal' => [
        'enabled' => filter_var(env('CATALOG_MULTIMODAL_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

    'rerank' => [
        'top_k' => (int) env('CATALOG_RERANK_TOP_K', 5),
        /** @deprecated No longer used for slot gating; kept for env compatibility only. */
        'min_score' => (float) env('CATALOG_RERANK_MIN_SCORE', 0),
        'weights' => [
            'vector' => (float) env('CATALOG_WEIGHT_VECTOR', 0.22),
            'dimension' => (float) env('CATALOG_WEIGHT_DIMENSION', 0.32),
            'style' => (float) env('CATALOG_WEIGHT_STYLE', 0.18),
            'subtype_boost' => (float) env('CATALOG_WEIGHT_SUBTYPE_BOOST', 0.12),
            'price' => (float) env('CATALOG_WEIGHT_PRICE', 0.06),
            'cutout' => (float) env('CATALOG_WEIGHT_CUTOUT', 0.04),
            'priority' => (float) env('CATALOG_WEIGHT_PRIORITY', 0.15),
            'diversity_penalty' => (float) env('CATALOG_WEIGHT_DIVERSITY_PENALTY', 0.08),
        ],
        'dimension_reject_ratio' => (float) env('CATALOG_DIMENSION_REJECT_RATIO', 0.95),
        'dimension_neutral_when_room_missing' => (float) env('CATALOG_DIMENSION_NEUTRAL', 0.5),
        'flooring_score_epsilon' => (float) env('CATALOG_FLOORING_SCORE_EPSILON', 0.03),
    ],

    'fallback' => [
        'popular_per_family' => (int) env('CATALOG_FALLBACK_POPULAR_LIMIT', 5),
    ],

];
