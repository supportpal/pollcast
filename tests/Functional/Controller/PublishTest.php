<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Tests\TestCase;

use function array_fill;
use function json_encode;
use function route;
use function str_repeat;

class PublishTest extends TestCase
{
    public function testPublish(): void
    {
        $channelName = 'private-channel';
        $channel = Channel::factory()->create(['name' => $channelName]);

        // Client events may only be sent to a channel the socket has joined.
        Member::factory()->create(['channel_id' => $channel->id, 'socket_id' => self::SOCKET_ID]);

        $event = 'client-test-event';
        $data = ['user_id' => 1];
        $response = $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => $channelName,
            'event'        => $event,
            'data'         => $data,
        ])
            ->assertStatus(200)
            ->assertJson([true]);

        $this->assertStringStartsWith('eyJ', $response->headers->get(Socket::HEADER) ?? '');

        $this->assertDatabaseHas('pollcast_message_queue', [
            'channel_id' => $channel->id,
            'member_id'  => null,
            'socket_id'  => self::SOCKET_ID,
            'event'      => $event,
            'payload'    => json_encode($data),
        ]);
    }

    /**
     * Only the server may originate an event a subscriber treats as authoritative, so a client
     * publishes under the client- prefix and nothing else - the application's own event names and
     * the pollcast: control events are out of its reach.
     */
    #[DataProvider('forgeableEventProvider')]
    public function testPublishRejectsEventsItMayNotOriginate(string $event): void
    {
        $channelName = 'private-channel';
        $channel = Channel::factory()->create(['name' => $channelName]);
        Member::factory()->create(['channel_id' => $channel->id, 'socket_id' => self::SOCKET_ID]);

        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => $channelName,
            'event'        => $event,
            'data'         => ['user_id' => 1],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('event');

        $this->assertDatabaseMissing('pollcast_message_queue', ['channel_id' => $channel->id]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forgeableEventProvider(): iterable
    {
        yield 'an application event'   => ['App\Events\OrderShipped'];
        yield 'an unprefixed event'    => ['test-event'];
        yield 'a presence roster add'  => ['pollcast:member_added'];
        yield 'a presence roster drop' => ['pollcast:member_removed'];
        yield 'a subscription ack'     => ['pollcast:subscription_succeeded'];
    }

    /** A client-supplied socket would let anyone withhold their event from a socket they choose. */
    public function testPublishRecordsTheAuthenticatedSocketNotAClientSuppliedOne(): void
    {
        $channelName = 'private-channel';
        $channel = Channel::factory()->create(['name' => $channelName]);
        Member::factory()->create(['channel_id' => $channel->id, 'socket_id' => self::SOCKET_ID]);

        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => $channelName,
            'event'        => 'client-test-event',
            'data'         => ['user_id' => 1, 'socket' => 'another-socket'],
        ])
            ->assertStatus(200)
            ->assertJson([true]);

        $this->assertDatabaseHas('pollcast_message_queue', [
            'channel_id' => $channel->id,
            'socket_id'  => self::SOCKET_ID,
        ]);
        $this->assertDatabaseMissing('pollcast_message_queue', ['socket_id' => 'another-socket']);
    }

    #[DataProvider('invalidPublishFieldProvider')]
    public function testPublishRejectsUnboundedFields(string $field, mixed $value): void
    {
        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => 'private-channel',
            'event'        => 'client-test-event',
            'data'         => ['user_id' => 1],
            $field         => $value,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidPublishFieldProvider(): iterable
    {
        yield 'channel name is not a string' => ['channel_name', ['private-channel']];
        yield 'channel name is unbounded'    => ['channel_name', str_repeat('a', 256)];
        yield 'event is not a string'        => ['event', ['client-test-event']];
        yield 'event is unbounded'           => ['event', 'client-' . str_repeat('a', 250)];
        yield 'data is not an array'         => ['data', 'user_id'];
        yield 'data is unbounded'            => ['data', array_fill(0, 101, 'a')];
    }

    /**
     * Joining a public channel is self-service - no authorisation callback runs for a name without
     * a guarded prefix - so anyone holding a socket would be able to publish into one.
     */
    #[DataProvider('publicChannelProvider')]
    public function testPublishRejectedOnAPublicChannel(string $channelName): void
    {
        $channel = Channel::factory()->create(['name' => $channelName]);
        Member::factory()->create(['channel_id' => $channel->id, 'socket_id' => self::SOCKET_ID]);

        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => $channelName,
            'event'        => 'client-test-event',
            'data'         => ['user_id' => 1],
        ])
            ->assertStatus(200)
            ->assertJson([false]);

        $this->assertDatabaseMissing('pollcast_message_queue', ['channel_id' => $channel->id]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicChannelProvider(): iterable
    {
        yield 'a public channel'      => ['public-channel'];
        yield 'an unprefixed channel' => ['channel'];

        // The prefix is matched case-sensitively, so these are public channels of their own.
        yield 'a miscased private'    => ['Private-channel'];
        yield 'a miscased presence'   => ['PRESENCE-channel'];
    }

    public function testPublishRequiresMembership(): void
    {
        $channel = Channel::factory()->create(['name' => 'presence-channel']);

        // A membership, but for a different socket - the caller still is not a member.
        Member::factory()->create(['channel_id' => $channel->id, 'socket_id' => 'someone-else']);

        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => 'presence-channel',
            'event'        => 'client-typing',
            'data'         => ['user_id' => 1],
        ])
            ->assertStatus(200)
            ->assertJson([false]);

        $this->assertDatabaseMissing('pollcast_message_queue', ['channel_id' => $channel->id]);
    }

    public function testPublishChannelNotFound(): void
    {
        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => 'fake-channel',
            'event'        => 'client-test-event',
            'data'         => ['user_id' => 1],
        ])
            ->assertStatus(200)
            ->assertJson([false]);
    }

    /**
     * Publishing resolves the channel by name too, so a spelling which is not the channel's own
     * must not reach its row here either.
     */
    public function testPublishDoesNotReachAnotherSpellingsChannel(): void
    {
        $channel = Channel::factory()->create(['name' => 'presence-channel']);

        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => 'Presence-channel',
            'event'        => 'client-test-event',
            'data'         => ['user_id' => 1],
        ])
            ->assertStatus(200)
            ->assertJson([false]);

        $this->assertDatabaseMissing('pollcast_message_queue', ['channel_id' => $channel->id]);
    }

    public function testPublishValidation(): void
    {
        $this->postAjax(route('supportpal.pollcast.publish'))
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The channel name field is required. (and 2 more errors)',
                'errors'  => [
                    'channel_name' => ['The channel name field is required.'],
                    'event'        => ['The event field is required.'],
                    'data'         => ['The data field is required.'],
                ]
            ]);
    }

    public function testPublishChannelValidation(): void
    {
        $this->postAjax(route('supportpal.pollcast.publish'), [
            'event'        => 'client-test-event',
            'data'         => ['user_id' => 1],
        ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The channel name field is required.',
                'errors'  => ['channel_name' => ['The channel name field is required.']]
            ]);
    }

    public function testPublishEventValidation(): void
    {
        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => 'fake-channel',
            'data'         => ['user_id' => 1],
        ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The event field is required.',
                'errors'  => ['event' => ['The event field is required.']]
            ]);
    }

    public function testPublishDataValidation(): void
    {
        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => 'fake-channel',
            'event'        => 'client-test-event',
        ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The data field is required.',
                'errors'  => ['data' => ['The data field is required.']]
            ]);
    }
}
