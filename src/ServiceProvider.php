<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Closure;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use SupportPal\Pollcast\Broadcasting\Socket;

use function array_merge;
use function config;
use function config_path;
use function is_array;
use function is_numeric;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(BroadcastManager $manager): void
    {
        $manager->extend('pollcast', function ($app) {
            return new PollcastBroadcaster($app[Socket::class]);
        });

        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('pollcast.php'),
        ], 'config');
    }

    public function register()
    {
        $this->registerSocket();
        $this->mergeConfigFrom(__DIR__.'/../config/broadcasting.php', 'broadcasting');
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'pollcast');
    }

    protected function registerSocket(): void
    {
        $this->app->singleton(Socket::class, function ($app) {
            return new Socket(
                $app['config'],
                $app['session.store'],
                $app->rebinding('request', $this->requestRebinder()),
            );
        });
    }

    protected function requestRebinder(): Closure
    {
        return function ($app, $request) {
            $app[Socket::class]->setRequest($request);
        };
    }

    /**
     * Merge the given configuration with the existing configuration.
     *
     * @param  string|mixed $path
     * @param  string|mixed $key
     * @return void
     */
    protected function mergeConfigFrom(mixed $path, mixed $key)
    {
        $config = config()->get($key, []);

        config()->set($key, $this->mergeConfig(require $path, $config));
    }

    /**
     * Merges the configs together and takes multi-dimensional arrays into account.
     * https://medium.com/@koenhoeijmakers/properly-merging-configs-in-laravel-packages-a4209701746d
     *
     * @param  mixed[] $original
     * @param  mixed[] $merging
     * @return mixed[]
     */
    protected function mergeConfig(array $original, array $merging): array
    {
        $array = array_merge($original, $merging);

        foreach ($original as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (! Arr::exists($merging, $key)) {
                continue;
            }

            if (is_numeric($key)) {
                continue;
            }

            $array[$key] = $this->mergeConfig($value, $merging[$key]);
        }

        return $array;
    }
}
