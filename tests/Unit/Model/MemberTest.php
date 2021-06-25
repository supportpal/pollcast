<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Unit\Model;

use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Tests\TestCase;

use function factory;

class MemberTest extends TestCase
{
    public function testChannelRelation(): void
    {
        $channel = factory(Channel::class)->create();
        $member = factory(Member::class)->create(['channel_id' => $channel->id]);

        $this->assertInstanceOf(Channel::class, $member->channel);
        $this->assertSame($channel->id, $member->channel->id);
    }
}
