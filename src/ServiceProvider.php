<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use SupportPal\Pollcast\Broadcasting\Socket;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(BroadcastManager $manager): void
    {
        $manager->extend('pollcast', function ($app) {
            $socket = new Socket($app['request']->session());

            return new PollcastBroadcaster($socket);
        });

        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
