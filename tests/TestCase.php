<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Artryazanov\WikipediaGamesDb\WikipediaGamesDbServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            WikipediaGamesDbServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Ensure queue runs synchronously during tests
        $app['config']->set('queue.default', 'sync');

        // Provide sensible defaults for package config in tests
        $app['config']->set('game-scraper.queue_connection', null);
        $app['config']->set('game-scraper.queue_name', 'default');
        $app['config']->set('game-scraper.root_category', 'Category:Video games');
        $app['config']->set('game-scraper.throttle_milliseconds', 0);
    }
}
