<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional;

use Mockery;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Tests\TestCase;

use function app;
use function now;
use function route;
use function session;

class VerifySocketIdMiddlewareTest extends TestCase
{
    public function testValidSession(): void
    {
        $channel = $this->setupChannelAndMember();

        $this->post(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(200);
    }

    public function testInvalidSession(): void
    {
        $channel = $this->setupChannelAndMember();

        app()->bind(Socket::class, function () {
            $mock = Mockery::mock(Socket::class);
            $mock->shouldReceive('id')
                ->andReturnNull();

            return $mock;
        });

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(500);
    }

    private function setupChannelAndMember(): Channel
    {
        $socketId = 'test';
        session([ Socket::UUID => $socketId ]);

        $channel = Channel::factory()->create([ 'name' => 'public-channel' ]);
        Member::factory()->create([ 'channel_id' => $channel->id, 'socket_id' => $socketId ]);

        return $channel;
    }
}
