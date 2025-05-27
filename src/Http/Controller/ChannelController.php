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
use SupportPal\Pollcast\Model\Member;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChannelController extends BroadcastController
{
    use UsePusherChannelConventions;

    public function __construct(private readonly Socket $socket)
    {
        //
    }

    public function connect(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'success',
            'id'     => $this->socket->createIdIfNotExists(),
            'time'   => Carbon::now()->toDateTimeString()
        ]);
    }

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        try {
            $channel = $request->channel_name;
            if ($this->isGuardedChannel($channel)) {
                $authMember = Broadcast::auth($request);
            }

            $this->socket->joinChannel($channel, $authMember ?? null);

            return new JsonResponse([true]);
        } catch (AccessDeniedHttpException $e) {
            $this->removeUnauthenticatedMember($request, $e);

            throw $e;
        }
    }

    public function unsubscribe(UnsubscribeRequest $request): JsonResponse
    {
        /** @var Channel|null $channel */
        $channel = Channel::query()
            ->where('name', $request->channel_name)
            ->first();

        if ($channel === null) {
            return new JsonResponse([false]);
        }

        /** @var Member|null $member */
        $member = Member::query()
            ->where('channel_id', $channel->id)
            ->where('socket_id', $this->socket->getId())
            ->first();

        if ($member === null) {
            return new JsonResponse([false]);
        }

        $this->socket->removeMemberFromChannel($member, $channel);

        return new JsonResponse([true]);
    }

    /**
     * @throws AccessDeniedException
     */
    protected function removeUnauthenticatedMember(Request $request, AccessDeniedHttpException $e): void
    {
        /** @var Channel|null $channel */
        $channel = Channel::query()
            ->where('name', $request->channel_name)
            ->first();
        if ($channel === null) {
            throw $e;
        }

        /** @var Member|null $member */
        $member = Member::query()
            ->where('channel_id', $channel->id)
            ->where('socket_id', $this->socket->getId())
            ->first();
        if ($member === null) {
            throw $e;
        }

        $this->socket->removeMemberFromChannel($member, $channel);
    }
}
