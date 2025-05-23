<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use SupportPal\Pollcast\Broadcasting\Socket;

class VerifySocketId
{
    public function __construct(private readonly Socket $socket)
    {
        //
    }

    /**
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $this->socket->hasId()) {
            throw new UnauthorizedException;
        }

        return $next($request);
    }
}
