<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Unit;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Orchestra\Testbench\Factories\UserFactory;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;
use SupportPal\Pollcast\PollcastBroadcaster;
use SupportPal\Pollcast\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function app;
use function config;
use function json_encode;
use function request;

class PollcastBroadcasterTest extends TestCase
{
    public function testAuth(): void
    {
        $channelName = 'fake-channel';
        [$user, $request] = $this->setupRequest($channelName);
        $broadcaster = $this->setupBroadcaster($request);
        $broadcaster->channel($channelName, function (User $user) {
            return true;
        });

        $this->assertEquals([
            'user_id' => $user->id,
            'user_info' => true
        ], $broadcaster->auth($request));
    }

    public function testAuthUserInfo(): void
    {
        $channelName = 'fake-channel';
        [$user, $request] = $this->setupRequest($channelName);
        $broadcaster = $this->setupBroadcaster($request);
        $broadcaster->channel($channelName, function (User $user) {
            return $user;
        });


        $this->assertEquals([
            'user_id' => $user->id,
            'user_info' => $user
        ], $broadcaster->auth($request));
    }

    public function testAuthUserInfoCustom(): void
    {
        $data = ['data' => '1234'];

        $channelName = 'fake-channel';
        [$user, $request] = $this->setupRequest($channelName);
        $broadcaster = $this->setupBroadcaster($request);
        $broadcaster->channel($channelName, function () use ($data) {
            return $data;
        });

        $this->assertEquals([
            'user_id' => $user->id,
            'user_info' => $data
        ], $broadcaster->auth($request));
    }

    public function testAuthInvalid(): void
    {
        $channelName = 'fake-channel';
        [, $request] = $this->setupRequest($channelName);
        $broadcaster = $this->setupBroadcaster($request);
        $broadcaster->channel($channelName, function (User $user) {
            return false;
        });

        $this->expectException(AccessDeniedHttpException::class);
        $broadcaster->auth($request);
    }

    public function testBroadcast(): void
    {
        $broadcaster = $this->setupBroadcaster(request());

        $channelName1 = 'public-channel';
        $channelName2 = 'private-channel';
        $userFunction = function () {
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

    public function testBroadcastGarbageCollection(): void
    {
        // Update lottery so garbage collection always runs.
        config(['pollcast.gc_lottery' => [1, 1]]);

        $broadcaster = $this->setupBroadcaster(request());

        $channelName1 = 'public-channel';
        $channel1 = Channel::factory()->create([
            'name'       => $channelName1,
            'updated_at' => Carbon::now()->subDays(2)->toDateTimeString()
        ]);

        $channelName2 = 'private-channel';
        $channel2 = Channel::factory()->create([
            'name'       => $channelName2,
            'updated_at' => Carbon::now()->subDay()->toDateTimeString()
        ]);

        $member1 = Member::factory()->create([
            'channel_id' => $channel2->id,
            'updated_at' => Carbon::now()->subHours(2)->toDateTimeString(),
            'data'       => ['member1']
        ]);
        $member2 = Member::factory()->create([
            'channel_id' => $channel2->id,
            'updated_at' => Carbon::now()->subMinutes(5)->toDateTimeString(),
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

        $broadcaster->broadcast([], 'test-event', []);

        $this->assertDatabaseMissing('pollcast_channel', ['id' => $channel1->id]);
        $this->assertDatabaseHas('pollcast_channel', ['id' => $channel2->id]);
        $this->assertDatabaseMissing('pollcast_channel_members', ['id' => $member1->id]);
        $this->assertDatabaseHas('pollcast_channel_members', ['id' => $member2->id]);
        $this->assertDatabaseMissing('pollcast_channel_members', ['id' => $member3->id]);
        $this->assertDatabaseHas('pollcast_message_queue', ['id' => $message1->id]);
        $this->assertDatabaseMissing('pollcast_message_queue', ['id' => $message2->id]);

        $this->assertDatabaseHas('pollcast_message_queue', ['event' => 'pollcast:member_removed', 'payload' => json_encode($member1->data)]);
        $this->assertDatabaseMissing('pollcast_message_queue', ['event' => 'pollcast:member_removed', 'payload' => json_encode($member3->data)]);
    }

    public function testBroadcastGarbageCollectionEmptyChannelOnly(): void
    {
        // Update lottery so garbage collection always runs.
        config(['pollcast.gc_lottery' => [1, 1]]);

        $broadcaster = $this->setupBroadcaster(request());

        $channel1 = Channel::factory()->create(['updated_at' => Carbon::now()->subDays(2)->toDateTimeString()]);
        $channel2 = Channel::factory()->create(['updated_at' => Carbon::now()->subDays(2)->toDateTimeString()]);

        $member = Member::factory()->create([
            'channel_id' => $channel1->id,
            'updated_at' => Carbon::now()->subSeconds(5)->toDateTimeString(),
        ]);

        $broadcaster->broadcast([], 'test-event', []);

        $this->assertDatabaseHas('pollcast_channel', ['id' => $channel1->id]);
        $this->assertDatabaseMissing('pollcast_channel', ['id' => $channel2->id]);
        $this->assertDatabaseHas('pollcast_channel_members', ['id' => $member->id]);
    }

    /**
     * @return PollcastBroadcaster
     */
    private function setupBroadcaster(Request $request): PollcastBroadcaster
    {
        $socket = new Socket(app('config'), app('session.store'), $request);

        return new PollcastBroadcaster($socket);
    }

    /**
     * @return mixed[]
     */
    private function setupRequest(string $channelName): array
    {
        /** @var User $user */
        $user = UserFactory::new()->create();
        $this->actingAs($user);

        $request = request()->merge(['channel_name' => $channelName]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return [$user, $request];
    }
}
