<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests;

use Illuminate\Contracts\Foundation\Application;
use SupportPal\Pollcast\ServiceProvider;

use function realpath;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testing']);

        $this->withFactories(__DIR__.'/../database/factories');

        $this->loadMigrationsFrom([
            '--database' => 'testing',
            '--path'     => realpath(__DIR__ . '/../database/migrations'),
        ]);
    }

    /**
     * Get the service provider class.
     */
    protected function getServiceProviderClass(): string
    {
        return ServiceProvider::class;
    }

    /**
     * Get application aliases.
     *
     * @param mixed|Application $app
     *
     * @return string[]
     */
    protected function getApplicationAliases($app): array
    {
        return [];
    }

    /**
     * Get application providers.
     *
     * @param mixed|Application $app
     *
     * @return string[]
     */
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param mixed|Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
    }
}
