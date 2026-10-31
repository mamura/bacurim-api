<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.connections.pgsql');

        if (! $app->environment('testing')
            || $app['config']->get('database.default') !== 'pgsql'
            || $connection['database'] !== 'bacurim_test'
            || $connection['username'] !== 'bacurim_test'
            || ! empty($connection['url'])) {
            throw new RuntimeException('Tests must use the dedicated PostgreSQL test database and role.');
        }

        return $app;
    }
}
