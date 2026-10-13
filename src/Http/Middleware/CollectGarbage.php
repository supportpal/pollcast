<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SupportPal\Pollcast\GarbageCollector;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function report;

/**
 * Runs garbage collection after the response has been sent.
 */
class CollectGarbage
{
    public function __construct(private readonly GarbageCollector $gc)
    {
        //
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->gc->collect();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
