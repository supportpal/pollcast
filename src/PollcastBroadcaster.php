<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Message;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PollcastBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    /** @var Socket */
    private $socket;

    /**
     * PollcastBroadcaster constructor.
     *
     * @param Socket $socket
     */
    public function __construct(Socket $socket)
    {
        $this->socket = $socket;
    }

    /**
     * Authenticate the incoming request for a given channel.
     *
     * @param mixed|Request $request
     * @return mixed
     */
    public function auth($request)
    {
        $channelName = $this->normalizeChannelName($request->channel_name);

        if ($this->isGuardedChannel($request->channel_name)
            && ! $this->retrieveUser($request, $channelName)
        ) {
            throw new AccessDeniedHttpException;
        }

        return parent::verifyUserCanAccessChannel($request, $channelName);
    }

    /**
     * Return the valid authentication response.
     *
     * @param mixed|Request $request
     * @param mixed $result
     * @return mixed[]
     */
    public function validAuthenticationResponse($request, $result)
    {
        $channelName = $this->normalizeChannelName($request->channel_name);
        $user        = $this->retrieveUser($request, $channelName);

        return [
            'user_id'   => $user->getAuthIdentifier(),
            'user_info' => $user,
        ];
    }

    /**
     * Broadcast the given event.
     *
     * @param  mixed[] $channels
     * @param  mixed|string $event
     * @param  mixed[] $payload
     * @return void
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        if (Arr::get($payload, 'socket') === null) {
            $payload['socket'] = $this->socket->id();
        }

        $events = new Collection;
        foreach ($channels as $channel) {
            $channel = Channel::query()->firstOrCreate(['name' => $channel]);

            $event = new Message([
                'channel_id' => $channel->id,
                'event'      => $event,
                'payload'    => $payload,
            ]);

            $events->push($event->setUuid()->touchTimestamps()->getAttributes());
        }

        Message::insert($events->toArray());
    }
}
