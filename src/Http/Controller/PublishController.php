<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Http\JsonResponse;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Http\Request\PublishRequest;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;

use function array_merge;

class PublishController
{
    use UsePusherChannelConventions;

    public function __construct(private readonly Socket $socket)
    {
        //
    }

    /**
     * Receive messages from the client.
     */
    public function publish(PublishRequest $request): JsonResponse
    {
        /** @var Channel|null $channel */
        $channel = Channel::query()
            ->where('name', $request->channel_name)
            ->first();

        if ($channel === null) {
            return new JsonResponse([false]);
        }

        // Client events are only allowed on private and presence channels, as they are on Pusher.
        if (! $this->isGuardedChannel($channel->name)) {
            return new JsonResponse([false]);
        }

        $isMember = Member::query()
            ->where('channel_id', $channel->id)
            ->where('socket_id', $this->socket->id())
            ->exists();

        if (! $isMember) {
            return new JsonResponse([false]);
        }

        (new Message([
            'channel_id' => $channel->id,
            'event'      => $request->event,
            // The socket names who to leave out of the delivery, so it is the publisher's own -
            // a client-supplied one would let anyone withhold an event from a socket they choose.
            'payload'    => array_merge($request->data, ['socket' => $this->socket->id()]),
        ]))->save();

        return new JsonResponse([true]);
    }
}
