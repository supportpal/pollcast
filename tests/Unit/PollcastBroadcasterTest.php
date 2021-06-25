<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Unit;

use Illuminate\Foundation\Auth\User;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\PollcastBroadcaster;
use SupportPal\Pollcast\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function factory;
use function json_encode;
use function request;
use function session;

class PollcastBroadcasterTest extends TestCase
{
    public function testAuth(): void
    {
        $broadcaster = $this->setupBroadcaster();

        $channelName = 'fake-channel';
        $broadcaster->channel($channelName, function (User $user) {
            return true;
        });

        [$user, $request] = $this->setupRequest($channelName);

        $this->assertEquals([
            'user_id' => $user->id,
            'user_info' => $user
        ], $broadcaster->auth($request));
    }

    public function testAuthInvalid(): void
    {
        $broadcaster = $this->setupBroadcaster();

        $channelName = 'fake-channel';
        $broadcaster->channel($channelName, function (User $user) {
            return false;
        });

        [, $request] = $this->setupRequest($channelName);

        $this->expectException(AccessDeniedHttpException::class);
        $broadcaster->auth($request);
    }

    public function testBroadcast(): void
    {
        $broadcaster = $this->setupBroadcaster();

        $channelName1 = 'fake-channel';
        $channelName2 = 'private-channel';
        $userFunction = function (User $user) {
            return true;
        };
        $broadcaster->channel($channelName1, $userFunction);
        $broadcaster->channel($channelName2, $userFunction);

        $eventName = 'test-event';
        $broadcaster->broadcast([$channelName1, $channelName2], $eventName, ['socket' => 'x']);

        $channels = Channel::get();
        foreach ($channels as $channel) {
            $this->assertDatabaseHas('pollcast_message_queue', [
                'channel_id' => $channel->id,
                'member_id'  => null,
                'event'      => $eventName,
                'payload'    => json_encode(['socket' => 'x']),
            ]);
        }

        $this->assertDatabaseCount('pollcast_message_queue', 2);
    }

    public function testBroadcastWithoutSocket(): void
    {
        $broadcaster = $this->setupBroadcaster();

        $channelName1 = 'fake-channel';
        $channelName2 = 'private-channel';
        $userFunction = function (User $user) {
            return true;
        };
        $broadcaster->channel($channelName1, $userFunction);
        $broadcaster->channel($channelName2, $userFunction);

        $eventName = 'test-event';
        $broadcaster->broadcast([$channelName1, $channelName2], $eventName, []);

        $channels = Channel::get();
        foreach ($channels as $channel) {
            $this->assertDatabaseHas('pollcast_message_queue', [
                'channel_id' => $channel->id,
                'member_id'  => null,
                'event'      => $eventName,
                'payload'    => json_encode(['socket' => 'test']),
            ]);
        }

        $this->assertDatabaseCount('pollcast_message_queue', 2);
    }

    /**
     * @return PollcastBroadcaster
     */
    private function setupBroadcaster(): PollcastBroadcaster
    {
        session([Socket::UUID => 'test']);

        $socket = new Socket($this->app['session.store']);

        return new PollcastBroadcaster($socket);
    }

    /**
     * @return mixed[]
     */
    private function setupRequest(string $channelName): array
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);

        $request = request()->merge(['channel_name' => $channelName]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return [$user, $request];
    }
}
