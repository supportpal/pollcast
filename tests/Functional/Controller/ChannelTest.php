<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional\Controller;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Mockery;
use Orchestra\Testbench\Factories\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Tests\TestCase;

use function json_encode;
use function route;
use function session;

class ChannelTest extends TestCase
{
    public function testConnect(): void
    {
        $date = '2021-06-01 00:00:00';
        Carbon::setTestNow(Carbon::parse($date));

        $socketMock = Mockery::mock(Socket::class)->makePartial();
        $socketMock->shouldReceive('hasId')->once()->andReturnFalse();
        $socketMock->shouldReceive('encode')->once()->andReturn($token = '...');
        $socketMock->shouldReceive('getIdFromSession')->once()->andReturn(null);
        $socketMock->shouldReceive('createId')->once()->andReturn($id = 'test');
        $this->app?->bind(Socket::class, fn () => $socketMock);

        $response = $this->postAjax(route('supportpal.pollcast.connect'))
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'id'     => null,
                'time'   => $date
            ]);

        $this->assertSame($token, $response->headers->get(Socket::HEADER));
    }

    public function testSubscribe(): void
    {
        $response = $this->postAjax(route('supportpal.pollcast.subscribe'), ['channel_name' => 'public-channel'])
            ->assertStatus(200)
            ->assertJson([true]);

        $this->assertStringStartsWith('eyJ', $response->headers->get(Socket::HEADER) ?? '');
    }

    public function testSubscribeGuardedChannel(): void
    {
        $channelName = 'channel';
        Broadcast::channel($channelName, function (User $user) {
            return true;
        });

        /** @var User $user */
        $user = UserFactory::new()->create();

        $this->actingAs($user)
            ->postAjax(route('supportpal.pollcast.subscribe'), ['channel_name' => 'presence-' . $channelName])
            ->assertStatus(200)
            ->assertJson([true]);
    }

    /**
     * Authorising a private channel returns the same user payload a presence channel gets. Acting
     * on it would hand the joiner the identities of everyone else on the channel, and announce
     * them to those members in turn - which is what choosing a private channel opted out of.
     */
    public function testSubscribePrivateChannelDisclosesNoOtherMembers(): void
    {
        $channelName = 'channel';
        Broadcast::channel($channelName, fn (User $user) => true);

        $channel = Channel::factory()->create(['name' => 'private-' . $channelName]);
        Member::factory()->create([
            'channel_id' => $channel->id,
            'socket_id'  => 'someone-else',
            'data'       => ['user_id' => 99, 'user_info' => ['name' => 'Someone Else']],
        ]);

        /** @var User $user */
        $user = UserFactory::new()->create();

        $this->actingAs($user)
            ->postAjax(route('supportpal.pollcast.subscribe'), ['channel_name' => 'private-' . $channelName])
            ->assertStatus(200)
            ->assertJson([true]);

        $this->assertDatabaseMissing('pollcast_message_queue', ['channel_id' => $channel->id]);
        $this->assertDatabaseHas('pollcast_channel_members', [
            'channel_id' => $channel->id,
            'socket_id'  => self::SOCKET_ID,
            'data'       => null,
        ]);
    }

    /**
     * Whether a channel needs authorising is decided by matching its prefix case-sensitively, so
     * `Presence-channel` is a public channel of its own and joining it is allowed. What must not
     * happen is that it lands the caller in the guarded `presence-channel`, which is what the
     * utf8mb4_bin collation on `pollcast_channel.name` is there to prevent: under the
     * case-insensitive default every spelling below selected that channel's row instead, joining
     * the caller to it without the authorisation callback ever running.
     */
    #[DataProvider('aliasingSpellingProvider')]
    public function testSubscribeNeverJoinsAnotherSpellingsChannel(string $channelName): void
    {
        Broadcast::channel('channel', fn (User $user) => true);

        $channel = Channel::factory()->create(['name' => 'presence-channel']);

        $this->postAjax(route('supportpal.pollcast.subscribe'), ['channel_name' => $channelName]);

        $this->assertDatabaseMissing('pollcast_channel_members', ['channel_id' => $channel->id]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function aliasingSpellingProvider(): iterable
    {
        yield 'capitalised'    => ['Presence-channel'];
        yield 'upper case'     => ['PRESENCE-channel'];
        yield 'mixed case'     => ['pReSeNcE-channel'];

        // utf8mb4_unicode_ci compares at primary strength, so these were equal to
        // `presence-channel` in the database while being different strings to PHP.
        yield 'fullwidth p'    => ["\u{FF50}resence-channel"];
        yield 'accented e'     => ["pr\u{00E9}sence-channel"];
        yield 'long s'         => ["pre\u{017F}ence-channel"];
        yield 'circled e'      => ["pr\u{24D4}sence-channel"];
    }

    public function testSubscribeChannelAuthErrorChannelNotFound(): void
    {
        $this->postAjax(route('supportpal.pollcast.subscribe'), ['channel_name' => 'private-channel'])
            ->assertStatus(403);
    }

    public function testSubscribeChannelAuthErrorMemberNotFound(): void
    {
        $channelName = 'private-channel';
        Channel::factory()->create(['name' => $channelName]);

        $this->postAjax(route('supportpal.pollcast.subscribe'), ['channel_name' => $channelName])
            ->assertStatus(403);
    }

    public function testSubscribeChannelAuthErrorMemberNoLongerAuthenticated(): void
    {
        $channelName = 'channel';
        $channel = Channel::factory()->create(['name' => 'presence-' . $channelName]);
        Broadcast::channel($channelName, function (User $user) {
            return false;
        });

        $socketId = 'test';
        session([ Socket::UUID => $socketId ]);
        Member::factory()->create(['channel_id' => $channel->id, 'socket_id' => $socketId]);

        /** @var User $user */
        $user = UserFactory::new()->create();

        $this->actingAs($user)
            ->postAjax(route('supportpal.pollcast.subscribe'), ['channel_name' => 'presence-' . $channelName])
            ->assertStatus(403);
    }

    public function testSubscribeChannelValidation(): void
    {
        $this->postAjax(route('supportpal.pollcast.subscribe'))
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The channel name field is required.',
                'errors'  => ['channel_name' => ['The channel name field is required.']]
            ]);
    }

    public function testUnsubscribe(string $channelName = 'public-channel'): Channel
    {
        $channel = $this->setupChannel($channelName);

        $socketId = 'test';
        session([Socket::UUID => $socketId]);

        Member::factory()->create(['channel_id' => $channel->id, 'socket_id' => $socketId]);

        $response = $this->postAjax(route('supportpal.pollcast.unsubscribe'), ['channel_name' => $channelName])
            ->assertStatus(200)
            ->assertJson([true]);

        $this->assertStringStartsWith('eyJ', $response->headers->get(Socket::HEADER) ?? '');

        return $channel;
    }

    public function testUnsubscribePresenceChannel(): void
    {
        $channel = $this->testUnsubscribe('presence-channel');

        $this->assertDatabaseHas('pollcast_message_queue', [
            'channel_id' => $channel->id,
            'member_id'  => null,
            'socket_id'  => 'test',
            'event'      => 'pollcast:member_removed',
            'payload'    => json_encode([]),
        ]);
    }

    /**
     * A private channel is authorised like a presence one but publishes nothing about who is on
     * it, so leaving one is not announced to the other members.
     */
    public function testUnsubscribePrivateChannel(): void
    {
        $channel = $this->testUnsubscribe('private-channel');

        $this->assertDatabaseMissing('pollcast_message_queue', ['channel_id' => $channel->id]);
    }

    public function testUnsubscribeChannelNotFound(): void
    {
        $this->postAjax(route('supportpal.pollcast.unsubscribe'), ['channel_name' => 'fake-channel'])
            ->assertStatus(200)
            ->assertJson([false]);
    }

    public function testUnsubscribeMemberNotFound(): void
    {
        $channelName = 'public-channel';
        $this->setupChannel($channelName);

        $this->postAjax(route('supportpal.pollcast.unsubscribe'), ['channel_name' => $channelName])
            ->assertStatus(200)
            ->assertJson([false]);
    }

    public function testUnsubscribeMemberDifferentSocketId(): void
    {
        $channelName = 'public-channel';
        $channel = $this->setupChannel($channelName);

        Member::factory()->create(['channel_id' => $channel->id]);

        $this->postAjax(route('supportpal.pollcast.unsubscribe'), ['channel_name' => $channelName])
            ->assertStatus(200)
            ->assertJson([false]);
    }

    /**
     * @param string $channelName
     * @return Channel
     */
    private function setupChannel(string $channelName): Channel
    {
        return Channel::factory()->create(['name' => $channelName]);
    }
}
