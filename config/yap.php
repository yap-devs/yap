<?php

return [
    'unit_price' => env('YAP_UNIT_PRICE', 0.05),  // 0.05 USD per GB
    'cutoff_point' => env('YAP_CUTOFF_POINT', 0.2),  // 0.2 GB (200 MB) as the cutoff point for traffic_unpaid
];
