<?php

return [
    'premium_home_media_keys' => [
        'premium_home_hero_background',
        'premium_home_cta_background',
        'premium_home_hero_avatar_top_right',
        'premium_home_hero_avatar_top_left',
        'premium_home_hero_avatar_bottom_left',
        'premium_home_hero_avatar_bottom_right',
        'premium_home_hero_dashboard',
        'premium_home_feature_dashboard',
        'premium_home_steps_arrow_right',
        'premium_home_steps_arrow_left',
        'premium_home_footer_payment_methods',
    ],
    'seo_media_keys' => [
        'seo_share_image',
    ],
    'seo_public_paths' => [
        '',
        'pricing',
        'contact',
        'product',
        'api-documentation',
        'developers',
        'faqs',
        'cookie-policy',
        'privacy',
        'terms-of-service',
    ],
    // Public sites indexed separately, as hreflang => host. Each uses its own
    // host for canonical URLs and the sitemap (see App\Support\SeoDomains).
    'seo_domain_alternates' => [
        'x-default' => 'botzo.net',
        'ar-SA' => 'botzo.sa',
    ],
    'seo_public_path_prefixes' => [
        'pages/',
    ],
];
