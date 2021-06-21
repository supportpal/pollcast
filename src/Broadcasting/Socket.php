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
}
