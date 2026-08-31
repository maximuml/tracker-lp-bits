<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject GET / HEAD requests to state-changing endpoints.
 *
 * Legacy NexusPHP routes were registered as `Route::match(['get', 'post'])`
 * which made mutations reachable via GET — a CSRF bypass vector
 * (`<img src="/ajax?action=removeToken&...">`). This middleware returns
 * 405 Method Not Allowed for any non-POST request, closing the vector
 * without requiring each controller to check the method individually.
 */
final class RejectGetMutations
{
    public function handle(Request $request, Closure $next): Response|JsonResponse|RedirectResponse
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'error' => 'method_not_allowed',
                    'message' => 'This endpoint requires a POST request.',
                ], 405);
            }

            return new Response('Method Not Allowed.', 405, [
                'Allow' => 'POST',
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        return $next($request);
    }
}
