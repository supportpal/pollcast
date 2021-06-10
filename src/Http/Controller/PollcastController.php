<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function receive(Request $request): JsonResponse
    {
        $query = Event::query()->select('*');

        $channels = $request->get('channels', []);

        foreach ($channels as $channel => $events) {
            foreach ($events as $event) {
                $query->orWhere(function ($query) use ($channel, $event, $request) {
                    // todo remove like wildcard? index these 3 columns?
                    $query->where('channels', 'like', '%"'.$channel.'"%')
                        ->where('event', '=', $event)
                        ->where('created_at', '>=', $request->get('time'));
                });
            }
        }

        $payload = $query->get()->map(function (Event $item, $key) use ($request) {
            $created = Carbon::createFromFormat('Y-m-d H:i:s', $item->created_at);
            $requested = Carbon::createFromFormat('Y-m-d H:i:s', $request->get('time'));

            $item->delay = $requested->diffInSeconds($created);
            $item->requested_at = $requested->toDateTimeString();

            return $item;
        });

        return new JsonResponse([
            'status'    => 'success',
            'time'     => Carbon::now()->toDateTimeString(),
            'payloads' => $payload
        ]);
    }
}
