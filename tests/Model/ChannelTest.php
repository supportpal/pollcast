<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Model;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Tests\TestCase;

use function factory;

class ChannelTest extends TestCase
{
    public function testNameAttribute(): void
    {
        $channel = factory(Channel::class)->create(['name' => 'fake-name']);

        $this->assertSame('fake-name', $channel->name);
    }

    public function testPublicChannelNameAttribute(): void
    {
        $channel = factory(Channel::class)->create(['name' => new \Illuminate\Broadcasting\Channel('fake-name')]);

        $this->assertSame('fake-name', $channel->name);
    }

    public function testPresenceChannelNameAttribute(): void
    {
        $channel = factory(Channel::class)->create(['name' => new PresenceChannel('fake-name')]);

        $this->assertSame('presence-fake-name', $channel->name);
    }

    public function testPrivateChannelNameAttribute(): void
    {
        $channel = factory(Channel::class)->create(['name' => new PrivateChannel('fake-name')]);

        $this->assertSame('private-fake-name', $channel->name);
    }
}
