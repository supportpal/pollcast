<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Http\Request\ReceiveRequest;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;

class SubscriptionController
{
    /** @var Socket */
    private $socket;

    public function __construct(Socket $socket)
    {
        $this->socket = $socket;
    }

    /**
     * Send messages to the client.
     */
    public function messages(ReceiveRequest $request): JsonResponse
    {
        $this->gc();

        $time = Carbon::now()->toDateTimeString();

        // Update the last active time in the channel.
        $this->updateLastActiveTime();

        $messages = new Collection;
        $channels = $this->getAuthorisedChannels();
        if ($channels->count() > 0) {
            $messages = $this->getMessagesForRequest($request, $channels);
        }

        return new JsonResponse([
            'status' => 'success',
            'time'   => $time,
            'events' => $messages
        ]);
    }

    /**
     * Garbage collection for old events.
     */
    protected function gc(): void
    {
        Channel::query()
            ->where('updated_at', '<', Carbon::now()->subDay()->toDateTimeString())
            ->delete();

        Message::query()
            ->where('created_at', '<', Carbon::now()->subSeconds(10)->toDateTimeString())
            ->delete();

        Member::query()
            ->with('channel')
            ->where('updated_at', '<', Carbon::now()->subSeconds(10)->toDateTimeString())
            ->each(function (Member $member) {
                /** @var Channel $channel */
                $channel = $member->channel;
                $this->socket->removeMemberFromChannel($member, $channel);
            });
    }

    protected function updateLastActiveTime(): void
    {
        Member::query()
            ->where('socket_id', $this->socket->id())
            ->update(['updated_at' => Date::now()]);
    }

    protected function getAuthorisedChannels(): Collection
    {
        return Member::query()
            ->where('socket_id', $this->socket->id())
            ->join('pollcast_channel', 'channel_id', '=', 'pollcast_channel.id')
            ->pluck('pollcast_channel.name', 'pollcast_channel.id');
    }

    protected function getMessagesForRequest(Request $request, Collection $channels): Collection
    {
        $member = Member::query()
            ->where('socket_id', $this->socket->id())
            ->firstOrFail();

        return Message::query()
            ->with('channel')
            ->where('created_at', '>=', $request->get('time'))
            ->where(function ($query) use ($member, $channels, $request) {
                $query->orWhere('member_id', $member->id);

                $channels->each(function (string $name, int $id) use ($request, $query) {
                    // Get requested events.
                    // If they ask for a channel they're not authorised to view then we'll ignore it.
                    $events = $request->get('channels', [])[$name] ?? [];

                    foreach ($events as $event) {
                        $query->orWhere(function ($query) use ($id, $event) {
                            $query->where('channel_id', $id)->where('event', $event);
                        });
                    }
                });
            })
            ->get()
            // Remove events triggered by the same member (prevent unnecessary events).
            ->filter(function (Message $message) {
                return Arr::get($message->payload, 'socket') !== $this->socket->id();
            });
    }
}
