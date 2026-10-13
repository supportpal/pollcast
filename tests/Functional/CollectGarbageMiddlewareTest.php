<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional;

use Illuminate\Support\Carbon;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;
use SupportPal\Pollcast\Tests\TestCase;

use function route;

class CollectGarbageMiddlewareTest extends TestCase
{
    public function testReceiveCollectsGarbage(): void
    {
        $channel = $this->setupChannelAndMember();
        $message = Message::factory()->create(['created_at' => Carbon::now()->subHours(2)->toDateTimeString()]);

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => Carbon::now()->toDateTimeString('microsecond'),
        ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('pollcast_message_queue', ['id' => $message->id]);
    }

    public function testConnectCollectsGarbage(): void
    {
        // Connect has no socket id yet; garbage collection must not need one.
        $message = Message::factory()->create(['created_at' => Carbon::now()->subHours(2)->toDateTimeString()]);

        $this->post(route('supportpal.pollcast.connect'), [], $this->getAjaxHeaders())
            ->assertStatus(200);

        $this->assertDatabaseMissing('pollcast_message_queue', ['id' => $message->id]);
    }

    public function testResumingMemberIsNotCollected(): void
    {
        // A member idle for over an hour polls again. The controller refreshes updated_at before
        // collection runs, so the member survives and receives a successful response.
        $channel = $this->setupChannelAndMember(Carbon::now()->subHours(2));
        $member = Member::query()->where('socket_id', self::SOCKET_ID)->firstOrFail();

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => Carbon::now()->toDateTimeString('microsecond'),
        ])
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('pollcast_channel_members', ['id' => $member->id]);
    }

    public function testCollectsAfterResponseIsSent(): void
    {
        // The stale message is still present while the controller runs; it only goes once the
        // response has been returned to the client.
        // The member must have joined before the message was queued to be sent it.
        $channel = $this->setupChannelAndMember(Carbon::now()->subHours(3));
        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'event'      => 'stale',
            'created_at' => Carbon::now()->subHours(2)->toDateTimeString(),
        ]);

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name => ['stale']],
            'time'     => Carbon::now()->subHours(3)->toDateTimeString('microsecond'),
        ])
            ->assertStatus(200)
            ->assertJsonPath('events.0.id', $message->id);

        $this->assertDatabaseMissing('pollcast_message_queue', ['id' => $message->id]);
    }

    private function setupChannelAndMember(?Carbon $joinedAt = null): Channel
    {
        $joinedAt ??= Carbon::now();

        $channel = Channel::factory()->create(['name' => 'public-channel']);
        Member::factory()->create([
            'channel_id' => $channel->id,
            'socket_id'  => self::SOCKET_ID,
            'created_at' => $joinedAt->toDateTimeString(),
            'updated_at' => $joinedAt->toDateTimeString(),
        ]);

        return $channel;
    }
}
