<?php

return [
    'enabled' => env('AFFILIATE_ENABLED', true),

    'cookie_days' => env('AFFILIATE_COOKIE_DAYS', 30),

    'attribution_type' => env('AFFILIATE_ATTRIBUTION_TYPE', 'first_click'),

    'allowed_gateways' => array_filter(explode(',', env('AFFILIATE_ALLOWED_GATEWAYS', 'stripe,alipay,usdt,github'))),

    'minimum_referrer_paid_amount' => env('AFFILIATE_MINIMUM_REFERRER_PAID_AMOUNT', 5),

    'minimum_referred_first_payment_amount' => env('AFFILIATE_MINIMUM_REFERRED_FIRST_PAYMENT_AMOUNT', 5),

    'minimum_commission_amount' => env('AFFILIATE_MINIMUM_COMMISSION_AMOUNT', 0.01),

    'pending_days' => env('AFFILIATE_PENDING_DAYS', 7),

    'commission_expires_days' => env('AFFILIATE_COMMISSION_EXPIRES_DAYS', 90),
];
