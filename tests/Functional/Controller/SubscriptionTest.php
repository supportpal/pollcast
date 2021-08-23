<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional\Controller;

use Illuminate\Support\Carbon;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;
use SupportPal\Pollcast\Tests\TestCase;

use function factory;
use function route;
use function session;

class SubscriptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $date = '2021-06-01 12:00:00';
        Carbon::setTestNow(Carbon::parse($date));
    }

    public function testMessagesNoneQueued(): void
    {
        [$channel,] = $this->setupChannelAndMember();

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => Carbon::now()->toDateTimeString(),
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString(),
                'events' => [],
            ]);
    }

    public function testMessagesOneQueued(): void
    {
        [$channel,] = $this->setupChannelAndMember();

        $event = 'test-event';
        $message = factory(Message::class)->create(['channel_id' => $channel->id, 'event' => $event]);

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => [$event]],
            'time'     => '2021-06-01 11:59:55',
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString(),
                'events' => [$message->load('channel')->toArray()],
            ]);
    }

    public function testMessagesMultipleQueued(): void
    {
        [$channel,] = $this->setupChannelAndMember();

        $event1 = 'test-event';
        $event2 = 'new-event';
        $message1 = factory(Message::class)->create(['channel_id' => $channel->id, 'event' => $event1]);
        factory(Message::class)->create(['channel_id' => $channel->id]);
        factory(Message::class)->create(['channel_id' => $channel->id, 'event' => $event2, 'created_at' => '2021-06-01 11:59:50']);
        $message2 = factory(Message::class)->create(['channel_id' => $channel->id, 'event' => $event2]);
        factory(Message::class)->create();

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => [$event1, $event2]],
            'time'     => '2021-06-01 11:59:55',
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString(),
                'events' => [$message1->load('channel')->toArray(), $message2->load('channel')->toArray()],
            ]);
    }

    public function testMessagesOrdering(): void
    {
        [$channel,] = $this->setupChannelAndMember();

        $event = 'test-event';
        $message1 = factory(Message::class)->create(['channel_id' => $channel->id, 'event' => $event, 'created_at' => '2021-06-01 11:59:56.123456']);
        $message2 = factory(Message::class)->create(['channel_id' => $channel->id, 'event' => $event, 'created_at' => '2021-06-01 11:59:56.023456']);
        $message3 = factory(Message::class)->create(['channel_id' => $channel->id, 'event' => $event, 'created_at' => '2021-06-01 11:59:56.123465']);

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => [$event]],
            'time'     => '2021-06-01 11:59:55',
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString(),
                'events' => [$message2->load('channel')->toArray(), $message1->load('channel')->toArray(), $message3->load('channel')->toArray()],
            ]);
    }

    public function testMessagesMemberUpdatedAtTouched(): void
    {
        [$channel, $member] = $this->setupChannelAndMember();

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => Carbon::now()->toDateTimeString(),
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString(),
                'events' => [],
            ]);

        $this->assertDatabaseHas('pollcast_channel_members', [
            'id'         => $member->id,
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }

    public function testMessagesMemberUpdatedAtTouchedMultipleChannels(): void
    {
        [$channel1, $member1] = $this->setupChannelAndMember();
        [$channel2, $member2] = $this->setupChannelAndMember();

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel1->name, $channel2->name],
            'time'     => Carbon::now()->toDateTimeString(),
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString(),
                'events' => [],
            ]);

        $this->assertDatabaseHas('pollcast_channel_members', [
            'id'         => $member1->id,
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('pollcast_channel_members', [
            'id'         => $member2->id,
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }

    public function testMessagesMemberNotFound(): void
    {
        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => ['fake-channel'],
            'time'     => Carbon::now()->toDateTimeString(),
        ])
            ->assertStatus(404);
    }

    public function testMessagesValidation(): void
    {
        $this->postAjax(route('supportpal.pollcast.receive'))
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The given data was invalid.',
                'errors'  => [
                    'channels' => ['The channels field is required.'],
                    'time'     => ['The time field is required.'],
                ]
            ]);
    }

    public function testMessagesChannelsValidation(): void
    {
        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => ['fake-channel'],
        ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The given data was invalid.',
                'errors'  => ['time' => ['The time field is required.']]
            ]);
    }

    /**
     * @return mixed[]
     */
    private function setupChannelAndMember(): array
    {
        $socketId = 'test';
        session([ Socket::UUID => $socketId ]);

        $channel = factory(Channel::class)->create([ 'name' => 'public-channel' ]);
        $member = factory(Member::class)->create([
            'channel_id' => $channel->id,
            'socket_id'  => $socketId,
            'updated_at' => Carbon::now()->subSeconds(5)->toDateTimeString(),
        ]);

        return [$channel, $member];
    }
}
