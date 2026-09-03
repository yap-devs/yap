<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();
        $connection = $application['config']->get('database.default');

        TestDatabaseGuard::ensureSafe(
            $application->environment(),
            (string) $application['config']->get("database.connections.$connection.driver"),
            (string) $application['config']->get("database.connections.$connection.database"),
        );

        return $application;
    }
}
