<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;

use function max;
use function random_int;

class GarbageCollector
{
    /** Cache lock held while a collection runs, so concurrent requests do not contend for the same rows. */
    public const string LOCK = 'pollcast:gc';

    /** Upper bound on how long a stuck collector may hold the lock, in seconds. */
    public const int LOCK_TTL = 60;

    /**
     * Stale messages are deleted this many rows at a time. Each statement is its own transaction,
     * so this bounds how long the message queue's row and gap locks are held against pollers.
     */
    public const int MESSAGE_BATCH = 1000;

    public function __construct(
        private readonly Socket $socket,
        private readonly Config $config,
        private readonly Cache $cache,
    ) {
        //
    }

    /**
     * Collect if the lottery is won and no other collector currently holds the lock.
     *
     * @return bool Whether a collection ran.
     */
    public function collect(): bool
    {
        if (! $this->hitsLottery()) {
            return false;
        }

        $store = $this->cache->store()->getStore();
        if (! $store instanceof LockProvider) {
            $this->run();

            return true;
        }

        // Non-blocking: if another request is already collecting there is nothing for us to do.
        return (bool) $store->lock(self::LOCK, self::LOCK_TTL)->get(function () {
            $this->run();

            return true;
        });
    }

    protected function run(): void
    {
        $this->deleteStaleMessages();
        $this->removeStaleMembers();
        $this->deleteEmptyChannels();
    }

    protected function deleteStaleMessages(): void
    {
        $cutoff = Carbon::now()->subHour()->toDateTimeString();

        do {
            $deleted = Message::query()
                ->where('created_at', '<', $cutoff)
                ->limit(self::MESSAGE_BATCH)
                ->delete();
        } while ($deleted >= self::MESSAGE_BATCH);
    }

    protected function removeStaleMembers(): void
    {
        Member::query()
            ->with('channel')
            ->where('updated_at', '<', Carbon::now()->subSeconds($this->memberTtl())->toDateTimeString())
            // Rows are deleted as we go, so page by id rather than by offset or later pages are skipped.
            ->eachById(function (Member $member) {
                /** @var Channel $channel */
                $channel = $member->channel;
                $this->socket->removeMemberFromChannel($member, $channel);
            });
    }

    protected function deleteEmptyChannels(): void
    {
        Channel::query()
            ->leftJoin('pollcast_channel_members', 'pollcast_channel_members.channel_id', '=', 'pollcast_channel.id')
            ->where('pollcast_channel.updated_at', '<', Carbon::now()->subDay()->toDateTimeString())
            ->whereNull('pollcast_channel_members.socket_id')
            ->delete();
    }

    /**
     * How long a member may go without polling before it is considered gone, in seconds.
     */
    protected function memberTtl(): int
    {
        $pollingInterval = (int) ($this->config->get('pollcast.polling_interval', 5000) / 1000);

        return 3 * max($pollingInterval, Socket::LIFETIME);
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
