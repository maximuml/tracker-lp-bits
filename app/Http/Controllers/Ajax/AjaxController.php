<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Support\Api;
use App\Support\Logger;
use Illuminate\Http\JsonResponse;

/**
 * Base class for the REST endpoints that replaced the /ajax action-string
 * dispatcher. Keeps the same `{ret, msg, data}` wire format and error
 * sanitisation the dispatcher used, so redirected callers see an identical
 * response contract.
 */
abstract class AjaxController extends Controller
{
    /** @param callable(): mixed $callback */
    protected function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json(Api::successWithContext($callback()));
        } catch (\Throwable $exception) {
            Logger::writeWithContext((string) ($exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);

            return response()->json(Api::failWithContext(self::clientSafeMessage($exception)));
        }
    }

    /**
     * Domain exceptions carry user-facing text the legacy JS displays;
     * infrastructure failures (PDO/QueryException, PHP engine errors) must not leak.
     */
    private static function clientSafeMessage(\Throwable $exception): string
    {
        if ($exception instanceof \PDOException || $exception instanceof \Error) {
            return 'Internal error';
        }

        return $exception->getMessage();
    }
}
