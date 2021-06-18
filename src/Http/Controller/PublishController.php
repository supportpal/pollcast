<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Http\JsonResponse;
use SupportPal\Pollcast\Http\Request\PublishRequest;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Message;

class PublishController
{
    /**
     * Receive messages from the client.
     */
    public function publish(PublishRequest $request): JsonResponse
    {
        $channel = Channel::query()->where('name', $request->channel_name)->firstOrFail();

        (new Message([
            'channel_id' => $channel->id,
            'event'      => $request->event,
            'payload'    => $request->data,
        ]))->save();

        return new JsonResponse([true]);
    }
}
