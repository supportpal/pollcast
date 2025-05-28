<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Exception\InvalidSocketException;
use Symfony\Component\HttpFoundation\Response;

class AddSocketId
{
    public function __construct(private readonly Socket $socket)
    {
        //
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        try {
            $response->headers->set('X-Socket-ID', $this->socket->encode());

            return $response;
        } catch (InvalidSocketException) {
            // If the socket ID cannot be encoded, we do not set the header.
            // This is to prevent sending an invalid socket ID to the client.
            // The client will need to handle this case appropriately.
            return $response;
        }
    }
}
