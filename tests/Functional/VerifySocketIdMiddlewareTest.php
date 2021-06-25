<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional;

use Mockery;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Tests\TestCase;

use function now;
use function route;

class VerifySocketIdMiddlewareTest extends TestCase
{
    public function testValidSession(): void
    {
        $this->post(route('supportpal.pollcast.receive'), [
            'channels' => ['test'],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(200);
    }

    public function testInvalidSession(): void
    {
        $mock = Mockery::mock('overload:' . Socket::class);
        $mock->shouldReceive('id')
            ->andReturnNull();

        $this->post(route('supportpal.pollcast.receive'), [
            'channels' => ['test'],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(500);
    }
}
