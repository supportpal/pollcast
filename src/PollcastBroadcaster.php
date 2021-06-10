<?php declare(strict_types=1);

namespace SupportPal\Pollcast;

use Carbon\Carbon;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Http\Request;
use SupportPal\Pollcast\Model\Event;

use function json_encode;

class PollcastBroadcaster implements Broadcaster
{
    /** @var int */
    private $gcMins;

    /**
     * @param mixed[] $config
     */
    public function __construct(array $config)
    {
        $this->gcMins = $config['gc_mins'] ?? 2;
    }

    /**
     * Authenticate the incoming request for a given channel.
     *
     * @param mixed|Request $request
     * @return mixed
     */
    public function auth($request)
    {
        // todo
    }

    /**
     * Return the valid authentication response.
     *
     * @param mixed|Request $request
     * @param mixed $result
     * @return mixed
     */
    public function validAuthenticationResponse($request, $result)
    {
        // todo
    }

    /**
     * Broadcast the given event.
     *
     * @param  mixed[] $channels
     * @param  mixed|string $event
     * @param  mixed[] $payload
     * @return void
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $this->gc();

        $event = new Event([
            'channels' => json_encode($channels),
            'event'    => $event,
            'payload'  => json_encode($payload),
        ]);
        $event->save();
    }

    /**
     * Garbage collection for old events.
     */
    protected function gc(): void
    {
        Event::query()
            ->where('created_at', '<', Carbon::now()->subMinutes($this->gcMins)->toDateTimeString())
            ->delete();
    }
}
