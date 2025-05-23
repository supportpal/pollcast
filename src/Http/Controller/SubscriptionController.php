<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Http\Request\ReceiveRequest;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;

class SubscriptionController
{
    private int $messagesFound = 0;

    public function __construct(private readonly Socket $socket)
    {
        //
    }

    /**
     * Send messages to the client.
     */
    public function messages(ReceiveRequest $request): JsonResponse
    {
        $time = Carbon::now();

        $memberQuery = Member::query()->where('socket_id', $this->socket->getIdFromRequest());
        $members = $memberQuery->get();
        if ($members->isEmpty()) {
            return new JsonResponse(['status' => 'error']);
        }

        // Update the last active time of the member.
        $memberQuery->update(['updated_at' => $time]);

        $messages = new Collection;
        $channels = $this->getAuthorisedChannels();
        if ($channels->count() > 0) {
            $messages = $this->getMessagesForRequest($time, $members, $request, $channels);
        }

        return new JsonResponse([
            'status' => 'success',
            'time'   => $time->toDateTimeString('microsecond'),
            'events' => $messages
        ]);
    }

    /**
     * @return Collection<string, string>
     */
    protected function getAuthorisedChannels(): Collection
    {
        return Member::query()
            ->where('pollcast_channel_members.socket_id', $this->socket->getIdFromRequest())
            ->join('pollcast_channel', 'pollcast_channel_members.channel_id', '=', 'pollcast_channel.id')
            ->pluck('pollcast_channel.name', 'pollcast_channel.id');
    }

    /**
     * @param Collection<int, Member> $members
     * @param Collection<string, string> $channels
     * @return LazyCollection<int, Message>
     */
    protected function getMessagesForRequest(
        Carbon $time,
        Collection $members,
        Request $request,
        Collection $channels
    ): LazyCollection {
        return Message::query()
            ->with('channel')
            ->where('created_at', '>=', $request->get('time'))
            ->where('created_at', '<', $time->toDateTimeString('microsecond'))
            ->where(function ($query) use ($members, $channels, $request) {
                $query->orWhereIn('member_id', $members->pluck('id'));

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
            ->orderBy('created_at')
            ->lazy(100)
            // Remove events triggered by the same member (prevent unnecessary events).
            ->filter(function (Message $message) {
                if ($this->messagesFound >= 10
                    || Arr::get($message->payload, 'socket') === $this->socket->getIdFromRequest()
                ) {
                    return false;
                }

                $this->messagesFound++;

                return true;
            });
    }
}
