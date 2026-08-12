<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional\Controller;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;
use SupportPal\Pollcast\Tests\TestCase;

use function array_fill;
use function array_fill_keys;
use function array_map;
use function implode;
use function range;
use function route;
use function session;
use function str_contains;
use function str_repeat;
use function str_starts_with;
use function substr_count;
use function vsprintf;

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
            'time'     => Carbon::now()->toDateTimeString('microsecond'),
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString('microsecond'),
                'events' => [],
            ]);
    }

    public function testMessagesOneQueued(): void
    {
        [$channel,] = $this->setupChannelAndMember();

        $event = 'test-event';
        $message = Message::factory()->create(['channel_id' => $channel->id, 'event' => $event, 'created_at' => '2021-06-01 11:59:57']);

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => [$event]],
            'time'     => '2021-06-01 11:59:55',
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString('microsecond'),
                'events' => [$message->load('channel')->toArray()],
            ]);
    }

    public function testMessagesMultipleQueued(): void
    {
        [$channel,] = $this->setupChannelAndMember();

        $event1 = 'test-event';
        $event2 = 'new-event';
        $message1 = Message::factory()->create(['channel_id' => $channel->id, 'event' => $event1, 'created_at' => '2021-06-01 11:59:56']);
        Message::factory()->create(['channel_id' => $channel->id]);
        Message::factory()->create(['channel_id' => $channel->id, 'event' => $event2, 'created_at' => '2021-06-01 11:59:50']);
        $message2 = Message::factory()->create(['channel_id' => $channel->id, 'event' => $event2, 'created_at' => '2021-06-01 11:59:57']);
        Message::factory()->create();

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => [$event1, $event2]],
            'time'     => '2021-06-01 11:59:55',
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString('microsecond'),
                'events' => [$message1->load('channel')->toArray(), $message2->load('channel')->toArray()],
            ]);
    }

    public function testMessagesMax10(): void
    {
        [$channel,] = $this->setupChannelAndMember();

        $event = 'test-event';
        $messages = Message::factory()
            ->count(15)
            ->create(['channel_id' => $channel->id, 'event' => $event, 'created_at' => '2021-06-01 11:59:56']);

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => [$event]],
            'time'     => '2021-06-01 11:59:55',
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString('microsecond'),
                'events' => $messages->load('channel')->take(10)->toArray(),
            ]);
    }

    public function testMessagesNotDuplicated(): void
    {
        [$channel,] = $this->setupChannelAndMember();

        $event = 'test-event';
        $message = Message::factory()->create(['channel_id' => $channel->id, 'event' => $event, 'created_at' => '2021-06-01 11:59:57']);

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => [$event]],
            'time'     => '2021-06-01 11:59:55',
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => $time = Carbon::now()->toDateTimeString('microsecond'),
                'events' => [$message->load('channel')->toArray()],
            ]);

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => [$event]],
            'time'     => $time,
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString('microsecond'),
                'events' => [],
            ]);
    }

    /**
     * A toOthers() broadcast names the sender's socket in its payload, which is what keeps the
     * sender from being sent back its own event.
     */
    public function testMessagesExcludeTheirOwnSender(): void
    {
        [$channel,] = $this->setupChannelAndMember();

        $event = 'test-event';
        Message::factory()->create([
            'channel_id' => $channel->id,
            'event'      => $event,
            'payload'    => ['socket' => self::SOCKET_ID],
            'created_at' => '2021-06-01 11:59:57',
        ]);
        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'event'      => $event,
            'payload'    => ['socket' => 'another-socket'],
            'created_at' => '2021-06-01 11:59:58',
        ]);

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => [$event]],
            'time'     => '2021-06-01 11:59:55',
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString('microsecond'),
                'events' => [$message->load('channel')->toArray()],
            ]);
    }

    /**
     * A caller may walk the `time` cursor as far back as they like, so the point they joined the
     * channel is the floor - the backlog from before that was never theirs to read.
     */
    public function testMessagesExcludeTheBacklogFromBeforeTheCallerJoined(): void
    {
        session([Socket::UUID => static::SOCKET_ID]);

        $channel = Channel::factory()->create(['name' => 'private-channel']);
        Member::factory()->create([
            'channel_id' => $channel->id,
            'socket_id'  => static::SOCKET_ID,
            'created_at' => '2021-06-01 11:59:57',
        ]);

        $event = 'test-event';
        Message::factory()->create(['channel_id' => $channel->id, 'event' => $event, 'created_at' => '2021-06-01 11:59:56']);
        $message = Message::factory()->create(['channel_id' => $channel->id, 'event' => $event, 'created_at' => '2021-06-01 11:59:58']);

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => [$event]],
            'time'     => '2000-01-01 00:00:00',
        ])
            ->assertStatus(200)
            ->assertJsonCount(1, 'events')
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString('microsecond'),
                'events' => [$message->load('channel')->toArray()],
            ]);
    }

    /**
     * Each channel is bounded by its own membership - joining a second one late does not reach
     * back into a channel the caller has been in all along, or vice versa.
     */
    public function testMessagesAreBoundedByEachChannelsOwnJoin(): void
    {
        session([Socket::UUID => static::SOCKET_ID]);

        $event = 'test-event';

        [$early, $late] = Collection::make(['2021-06-01 11:59:50', '2021-06-01 11:59:57'])
            ->map(function (string $joinedAt, int $i) use ($event) {
                $channel = Channel::factory()->create(['name' => 'private-channel-' . $i]);
                Member::factory()->create([
                    'channel_id' => $channel->id,
                    'socket_id'  => static::SOCKET_ID,
                    'created_at' => $joinedAt,
                ]);

                return [
                    'name'    => $channel->name,
                    'message' => Message::factory()->create([
                        'channel_id' => $channel->id,
                        'event'      => $event,
                        'created_at' => '2021-06-01 11:59:55',
                    ]),
                ];
            })
            ->all();

        $response = $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$early['name'] => [$event], $late['name'] => [$event]],
            'time'     => '2000-01-01 00:00:00',
        ])
            ->assertStatus(200)
            ->assertJsonCount(1, 'events');

        $this->assertSame($early['message']->id, $response->json('events.0.id'));
    }

    public function testMessagesOrdering(): void
    {
        [$channel,] = $this->setupChannelAndMember();

        $event = 'test-event';
        $message1 = Message::factory()->create(['channel_id' => $channel->id, 'event' => $event, 'created_at' => '2021-06-01 11:59:56.123456']);
        $message2 = Message::factory()->create(['channel_id' => $channel->id, 'event' => $event, 'created_at' => '2021-06-01 11:59:56.023456']);
        $message3 = Message::factory()->create(['channel_id' => $channel->id, 'event' => $event, 'created_at' => '2021-06-01 11:59:56.123465']);

        $params = [
            'channels' => [$channel->name => [$event]],
            'time'     => '2021-06-01 11:59:55',
        ];
        $response = $this->postAjax(route('supportpal.pollcast.receive'), $params)
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString('microsecond'),
            ]);

        $json = $response->decodeResponseJson();
        $this->assertArrayHasKey('events', $json);

        $expected = [$message2['id'], $message1['id'], $message3['id']];
        foreach ($expected as $order => $id) {
            $this->assertSame(
                $id,
                $json['events'][$order]['id'],
                vsprintf('Key %d value %s does not match expected value %s. The expected order is: %s', [
                    $order,
                    $json['events'][$order]['id'],
                    $id,
                    implode(', ', $expected)
                ])
            );
        }
    }

    public function testMessagesMemberUpdatedAtTouched(): void
    {
        [$channel, $member] = $this->setupChannelAndMember();

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => Carbon::now()->toDateTimeString('microsecond'),
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString('microsecond'),
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
            'time'     => Carbon::now()->toDateTimeString('microsecond'),
        ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'time'   => Carbon::now()->toDateTimeString('microsecond'),
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
            'time'     => Carbon::now()->toDateTimeString('microsecond'),
        ])
            ->assertStatus(200)
            ->assertJson(['status' => 'error']);
    }

    public function testMessagesValidation(): void
    {
        $this->postAjax(route('supportpal.pollcast.receive'))
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The channels field is required. (and 1 more error)',
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
                'message' => 'The time field is required.',
                'errors'  => ['time' => ['The time field is required.']]
            ]);
    }

    /**
     * The requested events are matched with one clause for the channel rather than one each, so
     * that the size of the request body does not decide how much query gets built.
     */
    public function testMessagesMatchEventsWithOneClausePerChannel(): void
    {
        [$channel,] = $this->setupChannelAndMember();

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => array_map(fn (int $i) => 'event-' . $i, range(1, 50))],
            'time'     => '2021-06-01 11:59:55',
        ])
            ->assertStatus(200);

        // Identifier quoting differs per grammar (sqlite/mysql), so match the bare table name.
        $select = Arr::first(
            $queries,
            fn (string $sql) => str_starts_with($sql, 'select') && str_contains($sql, 'pollcast_message_queue')
        );

        $this->assertNotNull($select, 'The messages were never selected.');
        $this->assertSame(1, substr_count($select, 'channel_id'), $select);
    }

    /**
     * The event list drives how much of the query is built, so an unbounded one turns a request
     * body into a far larger allocation on the server.
     *
     * @param mixed[] $channels
     */
    #[DataProvider('unboundedChannelsProvider')]
    public function testMessagesRejectsAnUnboundedChannelMap(array $channels, string $error): void
    {
        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => $channels,
            'time'     => Carbon::now()->toDateTimeString('microsecond'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors($error);
    }

    /**
     * @return iterable<string, array{mixed[], string}>
     */
    public static function unboundedChannelsProvider(): iterable
    {
        yield 'too many channels' => [
            array_fill_keys(array_map(fn (int $i) => 'channel-' . $i, range(1, 101)), []),
            'channels',
        ];

        yield 'too many events' => [
            ['public-channel' => array_fill(0, 101, 'test-event')],
            'channels.public-channel',
        ];

        yield 'an event which is not a string' => [
            ['public-channel' => [['test-event']]],
            'channels.public-channel.0',
        ];

        yield 'an unbounded event name' => [
            ['public-channel' => [str_repeat('a', 256)]],
            'channels.public-channel.0',
        ];
    }

    /**
     * An unparseable time would otherwise reach the query as a bound on created_at.
     */
    public function testMessagesRejectsATimeWhichIsNotADate(): void
    {
        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => ['public-channel' => []],
            'time'     => 'not-a-date',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('time');
    }

    /**
     * @return mixed[]
     */
    private function setupChannelAndMember(): array
    {
        $socketId = 'test';
        session([ Socket::UUID => $socketId ]);

        $channel = Channel::factory()->create([ 'name' => 'public-channel' ]);
        $member = Member::factory()->create([
            'channel_id' => $channel->id,
            'socket_id'  => $socketId,
            // Joined before any of the messages below were broadcast, which is the only way a
            // caller is entitled to read them.
            'created_at' => Carbon::now()->subMinute()->toDateTimeString(),
            'updated_at' => Carbon::now()->subSeconds(5)->toDateTimeString(),
        ]);

        return [$channel, $member];
    }
}
