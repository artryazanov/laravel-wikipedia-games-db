<?php

namespace Artryazanov\WikipediaGamesDb;

use Artryazanov\WikipediaGamesDb\Console\ScrapeWikipediaCommand;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Support\ServiceProvider;

class WikipediaGamesDbServiceProvider extends ServiceProvider
{
    /**
     * Register application services for the package.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/game-scraper.php', 'game-scraper');

        // Bind MediaWikiClient as a singleton
        $this->app->singleton(MediaWikiClient::class, function ($app) {
            return new MediaWikiClient(
                $app['config']->get('game-scraper.api_endpoint'),
                $app['config']->get('game-scraper.user_agent')
            );
        });

        // Bind InfoboxParser as a singleton
        $this->app->singleton(InfoboxParser::class, function () {
            return new InfoboxParser;
        });
    }

    /**
     * Bootstrap package services and resources.
     */
    public function boot(): void
    {
        // Register console command when running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                ScrapeWikipediaCommand::class,
            ]);
        }

        // Load package migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publish configuration and migrations
        $this->publishes([
            __DIR__.'/../config/game-scraper.php' => config_path('game-scraper.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'migrations');
    }
}
