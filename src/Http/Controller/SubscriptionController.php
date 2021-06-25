<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Http\Request\ReceiveRequest;
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
        $time = Carbon::now()->toDateTimeString();

        $member = Member::query()
            ->where('socket_id', $this->socket->id())
            ->firstOrFail();

        // Update the last active time of the member.
        $member->touch();

        $messages = new Collection;
        $channels = $this->getAuthorisedChannels();
        if ($channels->count() > 0) {
            $messages = $this->getMessagesForRequest($member, $request, $channels);
        }

        return new JsonResponse([
            'status' => 'success',
            'time'   => $time,
            'events' => $messages
        ]);
    }

    protected function getAuthorisedChannels(): Collection
    {
        return Member::query()
            ->where('socket_id', $this->socket->id())
            ->join('pollcast_channel', 'channel_id', '=', 'pollcast_channel.id')
            ->pluck('pollcast_channel.name', 'pollcast_channel.id');
    }

    protected function getMessagesForRequest(Member $member, Request $request, Collection $channels): Collection
    {
        return Message::query()
            ->with('channel')
            ->where('created_at', '>=', $request->get('time'))
            ->where(function ($query) use ($member, $channels, $request) {
                $query->orWhere('member_id', $member->id);

                $channels->each(function (string $name, string $id) use ($request, $query) {
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
