<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Broadcasting;

use Exception;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;

use function is_string;

class Socket
{
    use UsePusherChannelConventions;

    public function __construct(private readonly Request $request)
    {
        //
    }

    public function getIdFromRequest(): string
    {
        $id = $this->request->input('id');
        if (is_string($id)) {
            return $id;
        }

        throw new Exception;
    }

    public function hasId(): bool
    {
        try {
            $this->getIdFromRequest();
        } catch (Exception) {
            return false;
        }

        return true;
    }

    public function createIdIfNotExists(): string
    {
        if (! $this->hasId()) {
            $id = $this->createId();
        } else {
            $id = $this->getIdFromRequest();
        }

        return $id;
    }

    public function createId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * @param mixed[]|null $data
     */
    public function joinChannel(string $name, ?array $data = null): void
    {
        /** @var Channel $channel */
        $channel = Channel::query()->firstOrCreate(['name' => $name]);
        $channel->touch();

        /** @var Member $member */
        $member = Member::query()->firstOrCreate([
            'channel_id' => $channel->id,
            'socket_id'  => $this->getIdFromRequest(),
        ], ['data' => $data]);

        if ($data === null) {
            return;
        }

        $this->joinedPresenceChannel($channel, $member, $data);
    }

    public function removeMemberFromChannel(Member $member, Channel $channel): void
    {
        $member->delete();

        if (! $this->isGuardedChannel($channel->name)) {
            return;
        }

        (new Message([
            'channel_id' => $channel->id,
            'event'      => 'pollcast:member_removed',
            'payload'    => $member->data ?? [],
        ]))->save();
    }

    /**
     * @param mixed[] $memberData
     */
    protected function joinedPresenceChannel(Channel $channel, Member $member, array $memberData): void
    {
        // Broadcast subscription succeeded event to the member.
        (new Message([
            'channel_id' => $channel->id,
            'member_id'  => $member->id,
            'event'      => 'pollcast:subscription_succeeded',
            'payload'    => Member::query()->where('channel_id', $channel->id)->pluck('data'),
        ]))->save();

        // Broadcast member added event to everyone in the channel.
        (new Message([
            'channel_id' => $channel->id,
            'event'      => 'pollcast:member_added',
            'payload'    => $memberData,
        ]))->save();
    }
}
