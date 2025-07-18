<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Exception\InvalidSocketException;

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
        try {
            $this->socket->setId(
                ! empty($request->header(Socket::HEADER)) ? $this->socket->getIdFromRequest()
                    : $this->socket->getIdFromSession()
            );
        } catch (InvalidSocketException $e) {
            throw new UnauthorizedException($e->getMessage());
        }

        return $next($request);
    }
}
