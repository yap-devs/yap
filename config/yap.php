<?php

return [
    'unit_price' => env('YAP_UNIT_PRICE', 0.02),  // 0.02 USD per GB
    'github' => [
        'sponsor_webhook_secret' => env('GITHUB_SPONSOR_WEBHOOK_SECRET', 'your-github-sponsor-webhook-secret'),
        'sponsor_url' => env('GITHUB_SPONSOR_URL', 'https://api.github.com/sponsors'),
    ]
];
