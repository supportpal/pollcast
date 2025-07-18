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
            $this->socket->setId($this->getSocketId($request));
        } catch (InvalidSocketException $e) {
            throw new UnauthorizedException($e->getMessage());
        }

        return $next($request);
    }

    /**
     * @throws InvalidSocketException
     */
    private function getSocketId(Request $request): string
    {
        if (! empty($request->header(Socket::HEADER))) {
            return $this->socket->getIdFromRequest();
        }

        $id = $this->socket->getIdFromSession();

        if ($id === null) {
            throw new InvalidSocketException('Socket ID is not set.');
        }

        return $id;
    }
}
