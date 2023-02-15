<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Unit;

use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Tests\TestCase;

use function json_encode;
use function session;

class SocketTest extends TestCase
{
    public function testId(): void
    {
        $socketId = 'test';
        session([Socket::UUID => $socketId]);

        $this->assertSame((new Socket($this->app['session.store']))->id(), $socketId);
    }

    public function testJoinChannel(): void
    {
        $socketId = 'test';
        session([Socket::UUID => $socketId]);

        $socket = new Socket($this->app['session.store']);

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
        $socketId = 'test';
        session([Socket::UUID => $socketId]);

        $socket = new Socket($this->app['session.store']);

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
        $socketId = 'test';
        session([Socket::UUID => $socketId]);

        $socket = new Socket($this->app['session.store']);

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
        $socketId = 'test';
        session([Socket::UUID => $socketId]);

        $socket = new Socket($this->app['session.store']);

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
        $socketId = 'test';
        session([Socket::UUID => $socketId]);

        $socket = new Socket($this->app['session.store']);

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
