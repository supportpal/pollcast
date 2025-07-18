<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional;

use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Tests\TestCase;

use function array_filter;
use function array_merge;
use function now;
use function route;
use function session;
use function sprintf;

class VerifySocketIdMiddlewareTest extends TestCase
{
    public function testValidXSocketIdHeader(): void
    {
        $channel = $this->setupChannelAndMember();

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(200);
    }

    public function testNoXSocketIdHeader(): void
    {
        $channel = $this->setupChannelAndMember();

        $this->post(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(500);
    }

    public function testInvalidXSocketIdHeader(): void
    {
        $channel = $this->setupChannelAndMember();

        $headers = array_filter(array_merge($this->getAjaxHeaders(), [sprintf('HTTP_%s', Socket::HEADER) => 'abc']));

        $this->post(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ], $headers)
            ->assertStatus(500);
    }

    public function testFallbackToSession(): void
    {
        $channel = $this->setupChannelAndMember();

        session([Socket::UUID => self::SOCKET_ID]);

        $this->post(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(200);
    }

    private function setupChannelAndMember(): Channel
    {
        $channel = Channel::factory()->create(['name' => 'public-channel']);
        Member::factory()->create(['channel_id' => $channel->id, 'socket_id' => self::SOCKET_ID]);

        return $channel;
    }
}
