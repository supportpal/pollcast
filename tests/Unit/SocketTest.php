<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Unit;

use Exception;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Tests\TestCase;

use function json_encode;
use function request;

class SocketTest extends TestCase
{
    public function testCreateIfNotExists(): void
    {
        $socket = new Socket(request());
        $this->assertMatchesRegularExpression(
            '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i',
            $socket->createIdIfNotExists()
        );

        $socket = new Socket(request()->merge(['id' => $socketId = 'test']));
        $this->assertSame(
            $socketId,
            $socket->createIdIfNotExists()
        );
    }

    public function testGetIdFromRequest(): void
    {
        $request = request()->merge(['id' => $socketId = 'test']);

        $this->assertSame((new Socket($request))->getIdFromRequest(), $socketId);
    }

    public function testGetIdFromRequestMissing(): void
    {
        $this->expectException(Exception::class);

        (new Socket(request()))->getIdFromRequest();
    }

    public function testJoinChannel(): void
    {
        $socket = new Socket(request()->merge(['id' => $socketId = 'test']));

        $channelName = 'fake-channel';
        $socket->joinChannel($channelName);

        $channel = Channel::where('name', $channelName)->firstOrFail();
        $this->assertDatabaseHas('pollcast_channel_members', [
            'channel_id' => $channel->id,
            'socket_id'  => $socketId,
            'data'       => null,
        ]);
    }

    public function testJoinPresenceChannel(): void
    {
        $socket = new Socket(request()->merge(['id' => $socketId = 'test']));

        $data = ['user_id' => 1];
        $channelName = 'presence-channel';
        $socket->joinChannel($channelName, $data);

        $channel = Channel::where('name', $channelName)->firstOrFail();
        $member = Member::where('channel_id', $channel->id)->firstOrFail();

        $this->assertDatabaseHas('pollcast_channel_members', [
            'channel_id' => $channel->id,
            'socket_id'  => $socketId,
            'data'       => json_encode($data),
        ]);

        $this->assertDatabaseHas('pollcast_message_queue', [
            'channel_id' => $channel->id,
            'member_id'  => $member->id,
            'event'      => 'pollcast:subscription_succeeded',
            'payload'    => json_encode([$data]),
        ]);

        $this->assertDatabaseHas('pollcast_message_queue', [
            'channel_id' => $channel->id,
            'member_id'  => null,
            'event'      => 'pollcast:member_added',
            'payload'    => json_encode($data),
        ]);
    }

    public function testRemoveMemberFromChannel(): void
    {
        $socket = new Socket(request()->merge(['id' => $socketId = 'test']));

        $channel = Channel::factory()->create(['name' => 'fake-name']);
        $member = Member::factory()->create(['channel_id' => $channel->id]);

        $socket->removeMemberFromChannel($member, $channel);

        $this->assertDatabaseMissing('pollcast_channel_members', [
            'channel_id' => $channel->id,
            'socket_id'  => $socketId,
        ]);
    }

    public function testRemoveMemberFromPresenceChannel(): void
    {
        $socket = new Socket(request()->merge(['id' => $socketId = 'test']));

        $channel = Channel::factory()->create(['name' => 'presence-name']);
        $member = Member::factory()->create(['channel_id' => $channel->id]);

        $socket->removeMemberFromChannel($member, $channel);

        $this->assertDatabaseMissing('pollcast_channel_members', [
            'channel_id' => $channel->id,
            'socket_id'  => $socketId,
        ]);

        $this->assertDatabaseHas('pollcast_message_queue', [
            'channel_id' => $channel->id,
            'member_id'  => null,
            'event'      => 'pollcast:member_removed',
            'payload'    => json_encode([]),
        ]);
    }

    public function testRemoveMemberFromPrivateChannel(): void
    {
        $socket = new Socket(request()->merge(['id' => $socketId = 'test']));

        $channel = Channel::factory()->create(['name' => 'private-name']);
        $data = ['user_id' => 1];
        $member = Member::factory()->create(['channel_id' => $channel->id, 'data' => $data]);

        $socket->removeMemberFromChannel($member, $channel);

        $this->assertDatabaseMissing('pollcast_channel_members', [
            'channel_id' => $channel->id,
            'socket_id'  => $socketId,
        ]);

        $this->assertDatabaseHas('pollcast_message_queue', [
            'channel_id' => $channel->id,
            'member_id'  => null,
            'event'      => 'pollcast:member_removed',
            'payload'    => json_encode($member->data),
        ]);
    }
}
