<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use SupportPal\Pollcast\Broadcasting\Socket;

class VerifySocketId
{
    /** @var Socket */
    private $socket;

    public function __construct(Socket $socket)
    {
        $this->socket = $socket;
    }

    /**
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->socket->id() === null) {
            throw new UnauthorizedException;
        }

        return $next($request);
    }
}
