<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Http\Request\ReceiveRequest;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;

use function array_diff;
use function array_keys;
use function array_map;
use function array_values;
use function in_array;

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

        $memberQuery = Member::query()->where('socket_id', $this->socket->getId());
        $members = $memberQuery->get();
        if ($members->isEmpty()) {
            return new JsonResponse(['status' => 'error']);
        }

        $channels = $this->getAuthorisedChannels();
        $requested = array_map('strval', array_keys($request->input('channels', [])));
        $listening = $channels->filter(fn (string $name) => in_array($name, $requested, true));

        // Update the last active time of only the channels being listened to. The socket is shared by every page in
        // the browser, so a page which left without unsubscribing goes stale while the others keep polling.
        Member::query()
            ->whereIn('id', $members->whereIn('channel_id', $listening->keys())->pluck('id'))
            ->update(['updated_at' => $time]);

        $messages = new Collection;
        if ($channels->count() > 0) {
            $messages = $this->getMessagesForRequest($time, $members, $request, $channels);
        }

        return new JsonResponse([
            'status'  => 'success',
            'time'    => $time->toDateTimeString('microsecond'),
            'events'  => $messages,
            'members' => $this->getPresenceMembers($listening),
            // Channels the socket is no longer a member of, for example garbage collected while the page was in the
            // background, so the client can subscribe again.
            'missing' => array_values(array_diff($requested, $channels->all())),
        ]);
    }

    /**
     * The active members of each presence channel, so the client can keep its member list in sync.
     *
     * @param Collection<string, string> $channels
     * @return Collection<string, Collection<int, mixed>>
     */
    protected function getPresenceMembers(Collection $channels): Collection
    {
        $presence = $channels->filter(fn (string $name) => Str::startsWith($name, 'presence-'));
        $members = $this->socket->activeMembers($presence->keys()->all())->groupBy('channel_id');

        return $presence->mapWithKeys(fn (string $name, string $id) => [
            $name => $members->get($id, new Collection)->pluck('data')->values(),
        ]);
    }

    /**
     * @return Collection<string, string>
     */
    protected function getAuthorisedChannels(): Collection
    {
        return Member::query()
            ->where('pollcast_channel_members.socket_id', $this->socket->getId())
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
            ->where('created_at', '>=', $request->input('time'))
            ->where('created_at', '<', $time->toDateTimeString('microsecond'))
            // Remove events triggered by the same socket (prevent unnecessary events).
            ->where(function ($query) {
                $query->whereNull('socket_id')
                    ->orWhere('socket_id', '!=', $this->socket->getId());
            })
            ->where(function ($query) use ($members, $channels, $request) {
                $query->orWhereIn('member_id', $members->pluck('id'));

                $joinedAt = $members->pluck('created_at', 'channel_id');

                $channels->each(function (string $name, string $id) use ($request, $query, $joinedAt) {
                    // Get requested events.
                    // If they ask for a channel they're not authorised to view then we'll ignore it.
                    $events = $request->input('channels', [])[$name] ?? [];
                    if (empty($events)) {
                        return;
                    }

                    $query->orWhere(function ($query) use ($id, $events, $joinedAt) {
                        $query->where('channel_id', $id)
                            ->whereIn('event', $events)
                            ->where('created_at', '>=', $joinedAt->get($id));
                    });
                });
            })
            ->orderBy('created_at')
            ->lazy(100)
            ->filter(function () {
                if ($this->messagesFound >= 10) {
                    return false;
                }

                $this->messagesFound++;

                return true;
            })
            // Keep the keys sequential so the response is a JSON array with messages filtered out.
            ->values();
    }
}
