<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Unit;

use Illuminate\Foundation\Auth\User;
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

        $channelName1 = 'public-channel';
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
        $date = '2021-06-01 12:00:00';
        Carbon::setTestNow(Carbon::parse($date));

        $broadcaster = $this->setupBroadcaster();

        $channelName1 = 'public-channel';
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

    public function testBroadcastGarbageCollection(): void
    {
        // Update lottery so garbage collection always runs.
        config(['pollcast.gc_lottery' => [1, 1]]);

        $broadcaster = $this->setupBroadcaster();

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
            'updated_at' => Carbon::now()->subSeconds(45)->toDateTimeString(),
            'data'       => ['member1']
        ]);
        $member2 = Member::factory()->create([
            'channel_id' => $channel2->id,
            'updated_at' => Carbon::now()->subSeconds(5)->toDateTimeString(),
            'data'       => ['member2']
        ]);
        $member3 = Member::factory()->create([
            'channel_id' => $channel1->id,
            'updated_at' => Carbon::now()->subSeconds(45)->toDateTimeString(),
            'data'       => ['member3']
        ]);

        $message1 = Message::factory()->create([
            'channel_id' => $channel2->id,
            'created_at' => Carbon::now()->subSeconds(5)->toDateTimeString(),
        ]);
        $message2 = Message::factory()->create([
            'channel_id' => $channel2->id,
            'created_at' => Carbon::now()->subSeconds(45)->toDateTimeString(),
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

    public function testBroadcastGarbageCollectionWithDifferentInterval(): void
    {
        config(['pollcast.polling_interval' => 10000]);

        // Update lottery so garbage collection always runs.
        config(['pollcast.gc_lottery' => [1, 1]]);

        $broadcaster = $this->setupBroadcaster();

        $channel = Channel::factory()->create();

        $member1 = Member::factory()->create([
            'channel_id' => $channel->id,
            'updated_at' => Carbon::now()->subSeconds(45)->toDateTimeString(),
        ]);
        $member2 = Member::factory()->create([
            'channel_id' => $channel->id,
            'updated_at' => Carbon::now()->subSeconds(5)->toDateTimeString(),
        ]);
        $member3 = Member::factory()->create([
            'channel_id' => $channel->id,
            'updated_at' => Carbon::now()->subSeconds(65)->toDateTimeString(),
        ]);

        $message1 = Message::factory()->create([
            'channel_id' => $channel->id,
            'created_at' => Carbon::now()->subSeconds(5)->toDateTimeString(),
        ]);
        $message2 = Message::factory()->create([
            'channel_id' => $channel->id,
            'created_at' => Carbon::now()->subSeconds(143)->toDateTimeString(),
        ]);
        $message3 = Message::factory()->create([
            'channel_id' => $channel->id,
            'created_at' => Carbon::now()->subSeconds(45)->toDateTimeString(),
        ]);

        $broadcaster->broadcast([], 'test-event', []);

        $this->assertDatabaseHas('pollcast_channel_members', ['id' => $member1->id]);
        $this->assertDatabaseHas('pollcast_channel_members', ['id' => $member2->id]);
        $this->assertDatabaseMissing('pollcast_channel_members', ['id' => $member3->id]);
        $this->assertDatabaseHas('pollcast_message_queue', ['id' => $message1->id]);
        $this->assertDatabaseMissing('pollcast_message_queue', ['id' => $message2->id]);
        $this->assertDatabaseHas('pollcast_message_queue', ['id' => $message3->id]);
    }

    public function testBroadcastGarbageCollectionEmptyChannelOnly(): void
    {
        // Update lottery so garbage collection always runs.
        config(['pollcast.gc_lottery' => [1, 1]]);

        $broadcaster = $this->setupBroadcaster();

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
    private function setupBroadcaster(): PollcastBroadcaster
    {
        session([Socket::UUID => 'test']);

        $socket = new Socket(app('session.store'));

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
