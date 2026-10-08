<?php

use App\Services\TrafficRate;

test('traffic rates use exact decimal arithmetic and floor fractional bytes', function (int $bytes, string $rate, int $expected) {
    expect(TrafficRate::bill($bytes, $rate))->toBe($expected);
})->with([
    [101, '1.25', 126],
    [9, '0.10', 0],
    [123456789012345, '1.50', 185185183518517],
    [PHP_INT_MAX, '1.00', PHP_INT_MAX],
    [100, '0.00', 0],
]);

test('invalid rates and negative counters are rejected', function (int $bytes, string $rate) {
    expect(fn () => TrafficRate::bill($bytes, $rate))->toThrow(InvalidArgumentException::class);
})->with([[-1, '1'], [1, '-1'], [1, '0.001'], [1, '1000000'], [1, '1e2']]);

test('billed counter overflow is rejected', function () {
    expect(fn () => TrafficRate::bill(PHP_INT_MAX, '2'))->toThrow(OverflowException::class);
});
