<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Message;
use SupportPal\Pollcast\Model\User;

use function array_filter;
use function strcasecmp;

class ChannelController extends BroadcastController
{
    use UsePusherChannelConventions;

    /** @var Session */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function connect(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'success',
            'id'     => $this->session->getId(),
            'time'   => Carbon::now()->toDateTimeString()
        ]);
    }

    /**
     * @return JsonResponse|Response
     */
    public function subscribe(Request $request)
    {
        if ($this->isGuardedChannel($request->channel_name)) {
            $response = parent::authenticate($request);
        }

        $channel = Channel::query()->firstOrCreate(['name' => $request->channel_name]);
        $user = User::query()->firstOrCreate(['channel_id' => $channel->id, 'socket_id' => $this->session->getId()]);

        if ($this->isGuardedChannel($request->channel_name)) {
            // Broadcast subscription succeeded event to the user.
            (new Message([
                'channel_id' => $channel->id,
                'member_id'  => $user->id,
                'event'      => 'pollcast:subscription_succeeded',
                'payload'    => null, // todo all users in the channel
            ]))->save();

            // Broadcast member added event to everyone in the channel.
            (new Message([
                'channel_id' => $channel->id,
                'event'      => 'pollcast:member_added',
                'payload'    => null, // todo info on user who joined
            ]));
        }

        return $response ?? new JsonResponse(['true']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $channels = array_filter($this->session->get('channels', []), function ($channel) use ($request) {
            return strcasecmp($channel, $request->channel_name) === 0;
        });

        $this->session->put('channels', $channels);

        // todo broadcast 'pollcast:member_removed' to everyone, with info on user who left

        return new JsonResponse([true]);
    }
}
