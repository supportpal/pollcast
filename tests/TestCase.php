<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Testing\TestResponse;
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
        $app['config']->set('app.key', 'slHhRrJMrmlsM6oC0L1fJp5n4QS8pg7m');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('broadcasting.default', 'pollcast');
    }

    /**
     * Headers required for an AJAX request.
     *
     * @return array<string,string>
     */
    public function getAjaxHeaders(): array
    {
        return [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_Accept'           => 'application/json'
        ];
    }

    /**
     * Perform a POST that pretends to be an AJAX request.
     *
     * @param  string $route
     * @param  mixed[]  $data
     * @return TestResponse
     */
    public function postAjax(string $route, array $data = []): TestResponse
    {
        return $this->post($route, $data, $this->getAjaxHeaders());
    }
}
