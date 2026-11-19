<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety guard: the event server runs with `php artisan config:cache`
     * (required for stable concurrent requests under Apache mod_php on
     * Windows, see docs/presentation-performance/). A cached config ignores
     * phpunit.xml, so RefreshDatabase would run against the LIVE MySQL
     * database. Refuse to run before any migration/refresh can happen.
     * Fix: `php artisan config:clear`, run the tests, `php artisan config:cache`.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $config = $app['config'];
        if ($config->get('database.default') !== 'sqlite'
            || $config->get('database.connections.sqlite.database') !== ':memory:') {
            fwrite(STDERR, "\nABORTED: tests would use the live database (config is cached?). Run `php artisan config:clear` first.\n");
            exit(1);
        }

        return $app;
    }
}
