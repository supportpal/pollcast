<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\ServiceProvider;

class ServiceProvider extends ServiceProvider
{
    public function boot(BroadcastManager $manager): void
    {
        $manager->extend('polycast', function () {
            return new PollcastBroadcaster;
        });

        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
