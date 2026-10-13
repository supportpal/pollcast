<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;

use function random_int;

class GarbageCollector
{
    public function __construct(
        private readonly Socket $socket,
        private readonly Config $config,
    ) {
        //
    }

    public function collect(): void
    {
        if (! $this->hitsLottery()) {
            return;
        }

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
     * Determine if the odds hit the lottery.
     */
    protected function hitsLottery(): bool
    {
        $lottery = $this->config->get('pollcast.gc_lottery', [1, 10]);

        return random_int(1, $lottery[1]) <= $lottery[0];
    }
}
