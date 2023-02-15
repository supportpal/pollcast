<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Unit\Model;

use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Tests\TestCase;

class MemberTest extends TestCase
{
    public function testChannelRelation(): void
    {
        $channel = Channel::factory()->create();
        $member = Member::factory()->create(['channel_id' => $channel->id]);

        $this->assertInstanceOf(Channel::class, $member->channel);
        $this->assertSame($channel->id, $member->channel->id);
    }
}
