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
        $channel = $this->setupChannelAndMember();

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
        $channel = $this->setupChannelAndMember();

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
        $channel = $this->setupChannelAndMember();

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

    private function setupChannelAndMember(): Channel
    {
        $socketId = 'test';
        session([ Socket::UUID => $socketId ]);

        $channel = factory(Channel::class)->create([ 'name' => 'public-channel' ]);
        factory(Member::class)->create([ 'channel_id' => $channel->id, 'socket_id' => $socketId ]);

        return $channel;
    }
}