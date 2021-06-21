<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Http\Request\SubscribeRequest;
use SupportPal\Pollcast\Http\Request\UnsubscribeRequest;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Message;
use SupportPal\Pollcast\Model\User;

use function json_decode;

class ChannelController extends BroadcastController
{
    use UsePusherChannelConventions;

    /** @var Socket */
    private $socket;

    public function __construct(Socket $socket)
    {
        $this->socket = $socket;
    }

    public function connect(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'success',
            'id'     => $this->socket->id(),
            'time'   => Carbon::now()->toDateTimeString()
        ]);
    }

    /**
     * @return JsonResponse|Response
     */
    public function subscribe(SubscribeRequest $request)
    {
        if ($this->isGuardedChannel($request->channel_name)) {
            /** @var string $data */
            $data = parent::authenticate($request);
            $data = json_decode($data, true);
        }

        $channel = Channel::query()->firstOrCreate(['name' => $request->channel_name]);
        $user = User::query()->firstOrCreate([
            'channel_id' => $channel->id,
            'socket_id'  => $this->socket->id(),
            'data'       => $data['channel_data'] ?? null,
        ]);

        if (isset($data['channel_data'])) {
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
                'payload'    => $data['channel_data'],
            ]))->save();
        }

        return new JsonResponse(true);
    }

    public function unsubscribe(UnsubscribeRequest $request): JsonResponse
    {
        $channel = Channel::query()->where('name', $request->channel_name)->firstOrFail();

        $user = User::query()
            ->where('channel_id', $channel->id)
            ->where('socket_id', $this->socket->id())
            ->firstOrFail();

        $user->delete();

        if ($this->isGuardedChannel($request->channel_name)) {
            (new Message([
                'channel_id' => $channel->id,
                'event'      => 'pollcast:member_removed',
                'payload'    => $user->data,
            ]))->save();
        }

        return new JsonResponse([true]);
    }
}
