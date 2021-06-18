<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use SupportPal\Pollcast\Broadcasting\Socket;

class VerifySocketId
{
    /**
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $socket = new Socket($request->session());
        if ($socket->id() === null) {
            throw new UnauthorizedException;
        }

        return $next($request);
    }
}
