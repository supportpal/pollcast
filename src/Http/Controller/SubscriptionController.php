<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use SupportPal\Pollcast\Http\Request\ReceiveRequest;
use SupportPal\Pollcast\Model\Message;
use SupportPal\Pollcast\Model\User;

use function sprintf;

class SubscriptionController
{
    /** @var string */
    private $id;

    public function __construct(Session $session)
    {
        $this->id = $session->getId();
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
        // Delete old messages.
        Message::query()
            ->where('created_at', '<', Carbon::now()->subSeconds(30)->toDateTimeString())
            ->delete();

        // Delete users who are no longer active in the channel.
        User::query()
            ->where('updated_at', '<', Carbon::now()->subSeconds(30)->toDateTimeString())
            ->delete();
    }

    protected function updateLastActiveTime(): void
    {
        User::query()->where('socket_id', $this->id)->update(['updated_at' => Date::now()]);
    }

    protected function getAuthorisedChannels(): Collection
    {
        return User::query()
            ->where('socket_id', $this->id)
            ->join('pollcast_channel', 'channel_id', '=', 'pollcast_channel.id')
            ->pluck('pollcast_channel.name', 'pollcast_channel.id');
    }

    protected function getMessagesForRequest(Request $request, Collection $channels): Collection
    {
        $messages = Message::query()->orWhere('socket_id', $this->id);
        $channels->each(function (string $name, int $id) use ($request, $messages) {
            // Get requested events.
            // If they ask for a channel they're not authorised to view then we'll ignore it.
            $events = $request->get(sprintf('channels.%s', $name), []);

            foreach ($events as $event) {
                $messages->orWhere(function ($query) use ($id, $event, $request) {
                    $query->where('channel_id', $id)
                        ->where('event', $event)
                        ->where('created_at', '>=', $request->get('time'));
                });
            }
        });

        return $messages->get();
    }
}
