<?php

use Tests\Support\TestDatabaseGuard;

test('it accepts an in-memory sqlite database', function () {
    expect(fn () => TestDatabaseGuard::ensureSafe('testing', 'sqlite', ':memory:'))
        ->not->toThrow(RuntimeException::class);
});

test('it rejects a production database', function () {
    expect(fn () => TestDatabaseGuard::ensureSafe('testing', 'mariadb', 'yap_dev'))
        ->toThrow(RuntimeException::class, 'Refusing to run tests against an unsafe database');
});

test('it rejects a non-testing application environment', function () {
    expect(fn () => TestDatabaseGuard::ensureSafe('production', 'sqlite', ':memory:'))
        ->toThrow(RuntimeException::class, 'Refusing to run tests outside the testing environment');
});
