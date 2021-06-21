<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Contracts\Session\Session;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Message;
use SupportPal\Pollcast\Model\User;

use function uniqid;

class Socket
{
    use UsePusherChannelConventions;

    private const UUID = 'pollcast:uuid';

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

        /** @var User $user */
        $user = User::query()->firstOrCreate([
            'channel_id' => $channel->id,
            'socket_id'  => $this->id(),
        ], ['data' => $data]);

        if ($data === null) {
            return;
        }

        $this->joinedPresenceChannel($channel, $user, $data);
    }

    public function removeUserFromChannel(User $user, Channel $channel): void
    {
        $user->delete();

        if (! $this->isGuardedChannel($channel->name)) {
            return;
        }

        (new Message([
            'channel_id' => $channel->id,
            'event'      => 'pollcast:member_removed',
            'payload'    => $user->data,
        ]))->save();
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
     * @param mixed[] $userData
     */
    protected function joinedPresenceChannel(Channel $channel, User $user, array $userData): void
    {
        // Broadcast subscription succeeded event to the user.
        (new Message([
            'channel_id' => $channel->id,
            'member_id'  => $user->id,
            'event'      => 'pollcast:subscription_succeeded',
            'payload'    => User::query()->where('channel_id', $channel->id)->pluck('data'),
        ]))->save();

        // Broadcast member added event to everyone in the channel.
        (new Message([
            'channel_id' => $channel->id,
            'event'      => 'pollcast:member_added',
            'payload'    => $userData,
        ]))->save();
    }
}
