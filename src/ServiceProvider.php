<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use SupportPal\Pollcast\Broadcasting\Socket;

use function config_path;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(BroadcastManager $manager): void
    {
        $manager->extend('pollcast', function ($app) {
            $socket = new Socket($app['session.store']);

            return new PollcastBroadcaster($socket);
        });

        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('pollcast.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'pollcast');
    }
}
