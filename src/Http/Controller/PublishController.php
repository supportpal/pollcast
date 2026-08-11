<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Http\JsonResponse;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Http\Request\PublishRequest;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;

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
            ->where('socket_id', $this->socket->getId())
            ->exists();

        if (! $isMember) {
            return new JsonResponse([false]);
        }

        (new Message([
            'channel_id' => $channel->id,
            // Taken from the authenticated socket, never from the client's data.
            'socket_id'  => $this->socket->getId(),
            'event'      => $request->event,
            'payload'    => $request->data,
        ]))->save();

        return new JsonResponse([true]);
    }
}
