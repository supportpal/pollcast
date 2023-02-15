<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Unit\Model;

use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;
use SupportPal\Pollcast\Tests\TestCase;

class MessageTest extends TestCase
{
    public function testChannelRelation(): void
    {
        $channel = Channel::factory()->create();
        $message = Message::factory()->create(['channel_id' => $channel->id]);

        $this->assertInstanceOf(Channel::class, $message->channel);
        $this->assertSame($channel->id, $message->channel->id);
    }

    public function testMemberRelation(): void
    {
        $member = Member::factory()->create();
        $message = Message::factory()->create(['member_id' => $member->id]);

        $this->assertInstanceOf(Member::class, $message->member);
        $this->assertSame($member->id, $message->member->id);
    }
}
