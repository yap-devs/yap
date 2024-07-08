<?php

return [
    'unit_price' => env('YAP_UNIT_PRICE', 0.02),  // 0.02 USD per GB
    'payment' => [
        'usd_rmb_rate' => env('YAP_USD_RMB_RATE', 7.4),
        'alipay' => [
            'subject' => env('ALIPAY_SUBJECT', 'Yap Donation'),
        ]
    ],
    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET', 'your-github-webhook-secret'),
        'sponsor_url' => env('GITHUB_SPONSOR_URL', 'https://api.github.com/sponsors'),
    ]
];
