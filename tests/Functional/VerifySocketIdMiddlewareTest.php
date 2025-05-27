<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional;

use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Tests\TestCase;

use function now;
use function route;

class VerifySocketIdMiddlewareTest extends TestCase
{
    public function testValidSession(): void
    {
        $channel = $this->setupChannelAndMember();

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(200);
    }

    public function testInvalidSession(): void
    {
        $channel = $this->setupChannelAndMember();

        $this->post(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(500);
    }

    private function setupChannelAndMember(): Channel
    {
        $channel = Channel::factory()->create([ 'name' => 'public-channel' ]);
        Member::factory()->create([ 'channel_id' => $channel->id, 'socket_id' => self::SOCKET_ID ]);

        return $channel;
    }
}
