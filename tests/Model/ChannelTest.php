<?php declare(strict_types=1);

namespace Tests\Model;

use SupportPal\Pollcast\Model\Channel;
use Tests\TestCase;

use function factory;

class ChannelTest extends TestCase
{
    public function testAccessNameAttribute(): void
    {
        $channel = factory(Channel::class)->create(['name' => 'Fake name']);

        $this->assertSame('Fake name', $channel->name);
    }
}
