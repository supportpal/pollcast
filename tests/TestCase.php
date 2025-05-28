<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\ServiceProvider;
use Symfony\Component\HttpFoundation\Request as BaseRequest;

use function app;
use function array_filter;
use function array_merge;
use function realpath;
use function request;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    public const SOCKET_ID = 'test';

    protected ?string $token = null;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testing']);

        $this->loadMigrationsFrom([
            '--database' => 'testing',
            '--path'     => realpath(__DIR__ . '/../database/migrations'),
        ]);

        $socket = new Socket(app('config'), app('session.store'), request());
        $this->token = $socket->setId(self::SOCKET_ID)->encode();
    }

    /**
     * Get the service provider class.
     */
    protected static function getServiceProviderClass(): string
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
    protected function getApplicationAliases(mixed $app): array
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
    protected function getPackageProviders(mixed $app): array
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
    protected function getEnvironmentSetUp(mixed $app)
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
        $headers = array_filter(array_merge($this->getAjaxHeaders(), ['HTTP_X-Socket-ID' => $this->token]));

        return $this->post($route, $data, $headers);
    }

    protected function createRequest(?string $socketId = null): Request
    {
        $headers = array_merge($this->getAjaxHeaders(), ['HTTP_X-Socket-ID' => $socketId]);
        $base = new BaseRequest([], [], [], [], [], $headers);

        return Request::createFromBase($base);
    }
}
