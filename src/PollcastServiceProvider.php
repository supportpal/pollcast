<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class PollcastServiceProvider extends ServiceProvider
{
    public function boot(BroadcastManager $manager): void
    {
        $manager->extend('polycast', function (Application $app, $config) {
            return new PollcastBroadcaster($config);
        });

        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
