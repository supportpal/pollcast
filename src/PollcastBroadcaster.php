<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function config;
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
        if ($this->hitsLottery()) {
            $this->gc();
        }

        if (Arr::get($payload, 'socket') === null) {
            $payload['socket'] = $this->socket->id();
        }

        $messages = new Collection;
        foreach ($channels as $channel) {
            /** @var Channel $channel */
            $channel = Channel::query()->firstOrCreate(['name' => $channel]);

            $messages->push(Message::make($channel->id, $event, $payload));
        }

        Message::insert($messages->toArray());
    }

    /**
     * Garbage collection for old events.
     */
    protected function gc(): void
    {
        $pollingInterval = (int) config('pollcast.polling_interval', 5000);

        Message::query()
            ->where('created_at', '<', Carbon::now()->subMilliseconds($pollingInterval * 6)->toDateTimeString())
            ->delete();

        Member::query()
            ->with('channel')
            ->where('updated_at', '<', Carbon::now()->subMilliseconds($pollingInterval * 6)->toDateTimeString())
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
