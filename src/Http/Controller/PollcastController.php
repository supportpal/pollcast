<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use SupportPal\Pollcast\Http\Request\ReceiveRequest;
use SupportPal\Pollcast\Model\Event;

use function hash;

class PollcastController
{
    public function connect(Session $session): JsonResponse
    {
        return new JsonResponse([
            'status' => 'success',
            'id'     => hash('sha256', $session->getId()),
            'time'   => Carbon::now()->toDateTimeString()
        ]);
    }

    public function receive(ReceiveRequest $request): JsonResponse
    {
        $this->gc();

        $time = Carbon::now()->toDateTimeString();

        $query = Event::query()->select('*');
        foreach ($request->get('channels') as $channel => $events) {
            foreach ($events as $event) {
                $query->orWhere(function ($query) use ($channel, $event, $request) {
                    $query->where('channel', $channel)
                        ->where('event', $event)
                        ->where('created_at', '>=', $request->get('time'));
                });
            }
        }

        return new JsonResponse([
            'status' => 'success',
            'time'   => $time,
            'events' => $query->get()
        ]);
    }

    /**
     * Garbage collection for old events.
     */
    protected function gc(): void
    {
        Event::query()
            ->where('created_at', '<', Carbon::now()->subMinutes(2)->toDateTimeString())
            ->delete();
    }
}
