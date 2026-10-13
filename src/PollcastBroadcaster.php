<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Exception\InvalidSocketException;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Message;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;

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
    public function auth(mixed $request)
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
    public function validAuthenticationResponse(mixed $request, mixed $result)
    {
        $channelName = $this->normalizeChannelName($request->channel_name);
        $user        = $this->retrieveUser($request, $channelName);

        return [
            'user_id'   => $user->getAuthIdentifier(),
            'user_info' => $result,
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
    public function broadcast(array $channels, mixed $event, array $payload = [])
    {
        $socket = $this->pullSocket($payload);

        $messages = new Collection;
        foreach ($channels as $channel) {
            $channel = Channel::query()->firstOrCreate(['name' => $channel]);

            $message = new Message([
                'channel_id' => $channel->id,
                'socket_id'  => $socket,
                'event'      => $event,
                'payload'    => $payload,
            ]);

            $messages->push($message->setUuid()->touchTimestamps()->getAttributes());
        }

        Message::insert($messages->toArray());
    }

    /**
     * Pull the sender's socket out of the payload and resolve the id its token names.
     *
     * The X-Socket-ID header is a credential, so it is never persisted with the payload.
     *
     * @param  mixed[] $payload
     */
    protected function pullSocket(array &$payload): ?string
    {
        $socket = Arr::pull($payload, 'socket');

        if (! is_string($socket)) {
            return null;
        }

        try {
            return $this->socket->getIdFromToken($socket);
        } catch (InvalidSocketException) {
            // Nobody to exclude.
            return null;
        }
    }
}
