<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional\Controller;

use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Tests\TestCase;

use function factory;
use function json_encode;
use function route;

class PublishTest extends TestCase
{
    public function testPublish(): void
    {
        $channelName = 'public-channel';
        $channel = factory(Channel::class)->create(['name' => $channelName]);

        $event = 'test-event';
        $data = ['user_id' => 1];
        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => $channelName,
            'event'        => $event,
            'data'         => $data,
        ])
            ->assertStatus(200)
            ->assertJson([true]);

        $this->assertDatabaseHas('pollcast_message_queue', [
            'channel_id' => $channel->id,
            'member_id'  => null,
            'event'      => $event,
            'payload'    => json_encode($data),
        ]);
    }

    public function testPublishChannelNotFound(): void
    {
        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => 'fake-channel',
            'event'        => 'test-event',
            'data'         => ['user_id' => 1],
        ])
            ->assertStatus(404);
    }

    public function testPublishValidation(): void
    {
        $this->postAjax(route('supportpal.pollcast.publish'))
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The given data was invalid.',
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
            'event'        => 'test-event',
            'data'         => ['user_id' => 1],
        ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The given data was invalid.',
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
                'message' => 'The given data was invalid.',
                'errors'  => ['event' => ['The event field is required.']]
            ]);
    }

    public function testPublishDataValidation(): void
    {
        $this->postAjax(route('supportpal.pollcast.publish'), [
            'channel_name' => 'fake-channel',
            'event'        => 'test-event',
        ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The given data was invalid.',
                'errors'  => ['data' => ['The data field is required.']]
            ]);
    }
}
