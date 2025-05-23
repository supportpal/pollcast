<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional\Controller;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Mockery;
use Orchestra\Testbench\Factories\UserFactory;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Tests\TestCase;

use function json_encode;
use function route;

class ChannelTest extends TestCase
{
    public function testConnect(): void
    {
        $date = '2021-06-01 00:00:00';
        Carbon::setTestNow(Carbon::parse($date));

        $socketMock = Mockery::mock(Socket::class)->makePartial();
        $socketMock->shouldReceive('hasId')->once()->andReturnFalse();
        $socketMock->shouldReceive('createId')->once()->andReturn($id = 'test');
        $this->app->bind(Socket::class, fn () => $socketMock);

        $this->postAjax(route('supportpal.pollcast.connect'))
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'id'     => $id,
                'time'   => $date
            ]);
    }

    public function testSubscribe(): void
    {
        $route = route('supportpal.pollcast.subscribe', ['id' => 'test']);
        $this->postAjax($route, ['channel_name' => 'public-channel'])
            ->assertStatus(200)
            ->assertJson([true]);
    }

    public function testSubscribeGuardedChannel(): void
    {
        $channelName = 'channel';
        Broadcast::channel($channelName, function (User $user) {
            return true;
        });

        /** @var User $user */
        $user = UserFactory::new()->create();
        $route = route('supportpal.pollcast.subscribe', ['id' => 'test']);

        $this->actingAs($user)
            ->postAjax($route, ['channel_name' => 'presence-' . $channelName])
            ->assertStatus(200)
            ->assertJson([true]);
    }

    public function testSubscribeChannelAuthErrorChannelNotFound(): void
    {
        $route = route('supportpal.pollcast.subscribe', ['id' => 'test']);
        $this->post($route, ['channel_name' => 'private-channel'])
            ->assertStatus(403);
    }

    public function testSubscribeChannelAuthErrorMemberNotFound(): void
    {
        $channelName = 'private-channel';
        Channel::factory()->create(['name' => $channelName]);

        $route = route('supportpal.pollcast.subscribe', ['id' => 'test']);
        $this->post($route, ['channel_name' => $channelName])
            ->assertStatus(403);
    }

    public function testSubscribeChannelAuthErrorMemberNoLongerAuthenticated(): void
    {
        $channelName = 'channel';
        $channel = Channel::factory()->create(['name' => 'presence-' . $channelName]);
        Broadcast::channel($channelName, function (User $user) {
            return false;
        });

        Member::factory()->create(['channel_id' => $channel->id, 'socket_id' => $socketId = 'test']);

        /** @var User $user */
        $user = UserFactory::new()->create();

        $this->actingAs($user)
            ->postAjax(
                route('supportpal.pollcast.subscribe', ['id' => $socketId]),
                ['channel_name' => 'presence-' . $channelName]
            )
            ->assertStatus(403);
    }

    public function testSubscribeChannelValidation(): void
    {
        $route = route('supportpal.pollcast.subscribe', ['id' => 'test']);
        $this->postAjax($route)
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The channel name field is required.',
                'errors'  => ['channel_name' => ['The channel name field is required.']]
            ]);
    }

    public function testUnsubscribe(string $channelName = 'public-channel'): Channel
    {
        $channel = $this->setupChannel($channelName);

        Member::factory()->create(['channel_id' => $channel->id, 'socket_id' => $socketId = 'test']);

        $route = route('supportpal.pollcast.unsubscribe', ['id' => $socketId]);
        $this->postAjax($route, ['channel_name' => $channelName])
            ->assertStatus(200)
            ->assertJson([true]);

        return $channel;
    }

    public function testUnsubscribeGuardedChannel(): void
    {
        $channel = $this->testUnsubscribe('private-channel');

        $this->assertDatabaseHas('pollcast_message_queue', [
            'channel_id' => $channel->id,
            'member_id'  => null,
            'event'      => 'pollcast:member_removed',
            'payload'    => json_encode([]),
        ]);
    }

    public function testUnsubscribeChannelNotFound(): void
    {
        $route = route('supportpal.pollcast.unsubscribe', ['id' => 'test']);
        $this->postAjax($route, ['channel_name' => 'fake-channel'])
            ->assertStatus(200)
            ->assertJson([false]);
    }

    public function testUnsubscribeMemberNotFound(): void
    {
        $channelName = 'public-channel';
        $this->setupChannel($channelName);

        $route = route('supportpal.pollcast.unsubscribe', ['id' => 'test']);
        $this->postAjax($route, ['channel_name' => $channelName])
            ->assertStatus(200)
            ->assertJson([false]);
    }

    public function testUnsubscribeMemberDifferentSocketId(): void
    {
        $channelName = 'public-channel';
        $channel = $this->setupChannel($channelName);

        Member::factory()->create(['channel_id' => $channel->id]);

        $route = route('supportpal.pollcast.unsubscribe', ['id' => 'test']);
        $this->postAjax($route, ['channel_name' => $channelName])
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
