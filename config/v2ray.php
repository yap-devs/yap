<?php

return [
    'v2bridge' => [
        'path' => env('V2BRIDGE_PATH', app()->basePath('v2bridge/v2bridge')),
    ],
    'v2ray' => [
        'path' => env('V2RAY_PATH', '/usr/local/bin/v2ray'),
    ],
];
