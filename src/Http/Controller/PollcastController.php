<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use SupportPal\Pollcast\Http\Request\ReceiveRequest;
use SupportPal\Pollcast\Model\Event;

class PollcastController
{
    public function connect(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'success',
            'time'   => Carbon::now()->toDateTimeString()
        ]);
    }

    public function receive(ReceiveRequest $request): JsonResponse
    {
        $query = Event::query()->select('*');
        foreach ($request->get('channels') as $channel => $events) {
            foreach ($events as $event) {
                $query->orWhere(function ($query) use ($channel, $event, $request) {
                    // todo remove like wildcard? index these 3 columns?
                    $query->where('channels', 'like', '%"'.$channel.'"%')
                        ->where('event', '=', $event)
                        ->where('created_at', '>=', $request->get('time'));
                });
            }
        }

        return new JsonResponse([
            'status' => 'success',
            'time'   => Carbon::now()->toDateTimeString(),
            'events' => $query->get()->map(function (Event $item) use ($request) {
                $item->setAttribute('delay', $item->delay($request->get('time')));

                return $item;
            })
        ]);
    }
}
