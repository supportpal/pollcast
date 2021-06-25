<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Unit\Model;

use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;
use SupportPal\Pollcast\Tests\TestCase;

use function factory;

class MessageTest extends TestCase
{
    public function testChannelRelation(): void
    {
        $channel = factory(Channel::class)->create();
        $message = factory(Message::class)->create(['channel_id' => $channel->id]);

        $this->assertInstanceOf(Channel::class, $message->channel);
        $this->assertSame($channel->id, $message->channel->id);
    }

    public function testMemberRelation(): void
    {
        $member = factory(Member::class)->create();
        $message = factory(Message::class)->create(['member_id' => $member->id]);

        $this->assertInstanceOf(Member::class, $message->member);
        $this->assertSame($member->id, $message->member->id);
    }
}
