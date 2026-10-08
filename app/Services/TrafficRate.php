<?php

namespace App\Services;

use InvalidArgumentException;
use OverflowException;

class TrafficRate
{
    public static function bill(int $bytes, string $rate): int
    {
        if ($bytes < 0 || ! preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?\z/', $rate)) {
            throw new InvalidArgumentException('Invalid traffic counter or multiplier.');
        }

        $billed = bcmul((string) $bytes, $rate, 0);

        if (bccomp($billed, (string) PHP_INT_MAX, 0) > 0) {
            throw new OverflowException('Billed traffic exceeds the supported counter range.');
        }

        return (int) $billed;
    }
}
