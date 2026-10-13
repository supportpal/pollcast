<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Unit;

use Illuminate\Support\Carbon;
use SupportPal\Pollcast\GarbageCollector;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;
use SupportPal\Pollcast\Tests\TestCase;

use function app;
use function json_encode;

class GarbageCollectorTest extends TestCase
{
    public function testCollect(): void
    {
        $channelName1 = 'public-channel';
        $channel1 = Channel::factory()->create([
            'name'       => $channelName1,
            'updated_at' => Carbon::now()->subDays(2)->toDateTimeString()
        ]);

        $channelName2 = 'presence-channel';
        $channel2 = Channel::factory()->create([
            'name'       => $channelName2,
            'updated_at' => Carbon::now()->subDay()->toDateTimeString()
        ]);

        $member1 = Member::factory()->create([
            'channel_id' => $channel2->id,
            'updated_at' => Carbon::now()->subMinutes(4)->toDateTimeString(),
            'data'       => ['member1']
        ]);
        $member2 = Member::factory()->create([
            'channel_id' => $channel2->id,
            'updated_at' => Carbon::now()->subMinutes(2)->toDateTimeString(),
            'data'       => ['member2']
        ]);
        $member3 = Member::factory()->create([
            'channel_id' => $channel1->id,
            'updated_at' => Carbon::now()->subHours(2)->toDateTimeString(),
            'data'       => ['member3']
        ]);

        $message1 = Message::factory()->create([
            'channel_id' => $channel2->id,
            'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString(),
        ]);
        $message2 = Message::factory()->create([
            'channel_id' => $channel2->id,
            'created_at' => Carbon::now()->subHours(2)->toDateTimeString(),
        ]);

        app(GarbageCollector::class)->collect();

        $this->assertDatabaseMissing('pollcast_channel', ['id' => $channel1->id]);
        $this->assertDatabaseHas('pollcast_channel', ['id' => $channel2->id]);
        $this->assertDatabaseMissing('pollcast_channel_members', ['id' => $member1->id]);
        $this->assertDatabaseHas('pollcast_channel_members', ['id' => $member2->id]);
        $this->assertDatabaseMissing('pollcast_channel_members', ['id' => $member3->id]);
        $this->assertDatabaseHas('pollcast_message_queue', ['id' => $message1->id]);
        $this->assertDatabaseMissing('pollcast_message_queue', ['id' => $message2->id]);

        $this->assertDatabaseHas('pollcast_message_queue', [
            'event'     => 'pollcast:member_removed',
            // The removed member's own socket - there is no request socket during gc.
            'socket_id' => $member1->socket_id,
            'payload'   => json_encode($member1->data),
        ]);
        $this->assertDatabaseMissing('pollcast_message_queue', ['event' => 'pollcast:member_removed', 'payload' => json_encode($member3->data)]);
    }

    public function testMemberTtlScalesWithPollingInterval(): void
    {
        // A 2 minute polling interval outlives the 60s token, so members get 3 x 2 minutes.
        $this->app['config']->set('pollcast.polling_interval', 120000);

        $stale = Member::factory()->create(['updated_at' => Carbon::now()->subMinutes(7)->toDateTimeString()]);
        $live = Member::factory()->create(['updated_at' => Carbon::now()->subMinutes(5)->toDateTimeString()]);

        app(GarbageCollector::class)->collect();

        $this->assertDatabaseMissing('pollcast_channel_members', ['id' => $stale->id]);
        $this->assertDatabaseHas('pollcast_channel_members', ['id' => $live->id]);
    }

    public function testCollectEmptyChannelOnly(): void
    {
        $channel1 = Channel::factory()->create(['updated_at' => Carbon::now()->subDays(2)->toDateTimeString()]);
        $channel2 = Channel::factory()->create(['updated_at' => Carbon::now()->subDays(2)->toDateTimeString()]);

        $member = Member::factory()->create([
            'channel_id' => $channel1->id,
            'updated_at' => Carbon::now()->subSeconds(5)->toDateTimeString(),
        ]);

        app(GarbageCollector::class)->collect();

        $this->assertDatabaseHas('pollcast_channel', ['id' => $channel1->id]);
        $this->assertDatabaseMissing('pollcast_channel', ['id' => $channel2->id]);
        $this->assertDatabaseHas('pollcast_channel_members', ['id' => $member->id]);
    }
}
