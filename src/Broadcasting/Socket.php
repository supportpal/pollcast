<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Contracts\Session\Session;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;

use function uniqid;

class Socket
{
    use UsePusherChannelConventions;

    public const UUID = 'pollcast:uuid';

    /** @var Session */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function id(): ?string
    {
        return $this->create()->getId();
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
            'socket_id'  => $this->id(),
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

        Message::insert([
            Message::make($channel->id, 'pollcast:member_removed', $member->data ?? [])
        ]);
    }

    protected function create(): self
    {
        if ($this->getId() === null) {
            $this->session->put(self::UUID, uniqid('pollcast-', true));
        }

        return $this;
    }

    protected function getId(): ?string
    {
        return $this->session->get(self::UUID);
    }

    /**
     * @param mixed[] $memberData
     */
    protected function joinedPresenceChannel(Channel $channel, Member $member, array $memberData): void
    {
        // Broadcast subscription succeeded event to the member.
        $messages = [
            Message::make(
                $channel->id,
                'pollcast:subscription_succeeded',
                Member::query()->where('channel_id', $channel->id)->pluck('data')->all(),
                $member->id
            ),
            Message::make($channel->id, 'pollcast:member_added', $memberData),
        ];

        Message::insert($messages);
    }
}
