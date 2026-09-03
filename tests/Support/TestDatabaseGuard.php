<?php

namespace Tests\Support;

use RuntimeException;

final class TestDatabaseGuard
{
    public static function ensureSafe(string $environment, string $driver, string $database): void
    {
        if ($environment !== 'testing') {
            throw new RuntimeException('Refusing to run tests outside the testing environment.');
        }

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException('Refusing to run tests against an unsafe database.');
        }
    }
}
