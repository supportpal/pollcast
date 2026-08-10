<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Exception\InvalidSocketException;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function config;
use function is_string;
use function random_int;

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
        if ($this->hitsLottery()) {
            $this->gc();
        }

        $payload = $this->replaceSocketToken($payload);

        $messages = new Collection;
        foreach ($channels as $channel) {
            $channel = Channel::query()->firstOrCreate(['name' => $channel]);

            $message = new Message([
                'channel_id' => $channel->id,
                'event'      => $event,
                'payload'    => $payload,
            ]);

            $messages->push($message->setUuid()->touchTimestamps()->getAttributes());
        }

        Message::insert($messages->toArray());
    }

    /**
     * Swap the sender's socket token for the socket id it names.
     *
     * For a toOthers() broadcast Laravel copies the raw X-Socket-ID header into the payload, and
     * for this driver that header is the signed token proving socket identity. The payload is
     * persisted and then served to every other member of the channel, so storing the token as-is
     * would hand each of them a working credential for the sender's socket. The id it names is
     * all the delivery side needs to leave the sender out of its own broadcast.
     *
     * @param  mixed[] $payload
     * @return mixed[]
     */
    protected function replaceSocketToken(array $payload): array
    {
        if (! isset($payload['socket'])) {
            return $payload;
        }

        try {
            $payload['socket'] = is_string($payload['socket'])
                ? $this->socket->getIdFromToken($payload['socket'])
                : null;
        } catch (InvalidSocketException) {
            // Anything we cannot resolve to a socket names nobody to exclude.
            $payload['socket'] = null;
        }

        return $payload;
    }

    /**
     * Garbage collection for old events.
     */
    protected function gc(): void
    {
        Message::query()
            ->where('created_at', '<', Carbon::now()->subHour()->toDateTimeString())
            ->delete();

        Member::query()
            ->with('channel')
            ->where('updated_at', '<', Carbon::now()->subHour()->toDateTimeString())
            ->each(function (Member $member) {
                /** @var Channel $channel */
                $channel = $member->channel;
                $this->socket->removeMemberFromChannel($member, $channel);
            });

        Channel::query()
            ->leftJoin('pollcast_channel_members', 'pollcast_channel_members.channel_id', '=', 'pollcast_channel.id')
            ->where('pollcast_channel.updated_at', '<', Carbon::now()->subDay()->toDateTimeString())
            ->whereNull('pollcast_channel_members.socket_id')
            ->delete();
    }

    /**
     * Determine if the odds hit the lottery (1 in 10).
     *
     * @return bool
     */
    protected function hitsLottery(): bool
    {
        $lottery = config('pollcast.gc_lottery', [1, 10]);

        return random_int(1, $lottery[1]) <= $lottery[0];
    }
}
