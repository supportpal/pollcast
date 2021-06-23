<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Http\Request\SubscribeRequest;
use SupportPal\Pollcast\Http\Request\UnsubscribeRequest;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\User;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
            'time'   => Carbon::now()->toDateTimeString()
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function subscribe(SubscribeRequest $request)
    {
        try {
            $channel = $request->channel_name;
            if ($this->isGuardedChannel($channel)) {
                $authUser = Broadcast::auth($request);
            }

            $this->socket->joinChannel($channel, $authUser ?? null);

            return new JsonResponse(true);
        } catch (AccessDeniedHttpException $e) {
            $this->removeUnauthenticatedUser($request, $e);
        }
    }

    public function unsubscribe(UnsubscribeRequest $request): JsonResponse
    {
        /** @var Channel $channel */
        $channel = Channel::query()
            ->where('name', $request->channel_name)
            ->firstOrFail();

        /** @var User $user */
        $user = User::query()
            ->where('channel_id', $channel->id)
            ->where('socket_id', $this->socket->id())
            ->firstOrFail();

        $this->socket->removeUserFromChannel($user, $channel);

        return new JsonResponse([true]);
    }

    /**
     * @throws AccessDeniedException
     */
    protected function removeUnauthenticatedUser(Request $request, AccessDeniedHttpException $e): void
    {
        /** @var Channel|null $channel */
        $channel = Channel::query()
            ->where('name', $request->channel_name)
            ->first();
        if ($channel === null) {
            throw $e;
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('channel_id', $channel->id)
            ->where('socket_id', $this->socket->id())
            ->first();
        if ($user === null) {
            throw $e;
        }

        $this->socket->removeUserFromChannel($user, $channel);

        throw $e;
    }
}
