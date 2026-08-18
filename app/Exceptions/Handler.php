<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Illuminate\View\ViewException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Laravel\Passport\Exceptions\AuthenticationException as PassportAuthenticationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        if (app()->runningInConsole()) {
            return;
        }
        $request = request();
        $permissionDenied = function (InsufficientPermissionException $e) use ($request) {
            if ($request->expectsJson()) {
                return response()->json(\App\Support\Api::failWithContext($e->getMessage(), $request->all()), 403);
            }
            try {
                \App\Support\LegacyResponse::permissionDenied();
            } catch (HttpResponseException $hre) {
                return $hre->getResponse();
            }
        };
        $this->renderable(function (InsufficientPermissionException $e) use ($permissionDenied) {
            return $permissionDenied($e);
        });
        $this->renderable(function (ViewException $e) use ($permissionDenied) {
            $previous = $e->getPrevious();
            while ($previous instanceof ViewException && $previous->getPrevious() !== null) {
                $previous = $previous->getPrevious();
            }
            if ($previous instanceof InsufficientPermissionException) {
                return $permissionDenied($previous);
            }

            return null;
        });
        $this->renderable(function (PassportAuthenticationException $e) use ($request) {
            return response()->redirectTo(sprintf("%s/login.php?returnto=%s", $request->getSchemeAndHttpHost(), urlencode($request->fullUrl())));
        });

        //Other Only handle in json request
        if (!$request->expectsJson() && !$request->ajax()) {
            $this->renderable(function (NexusException $e) {
                return redirect(url('/error?error=' . urlencode($e->getMessage())));
            });
            return;
        }

        $this->renderable(function (AuthenticationException $e) {
            return response()->json(\App\Support\Api::failWithContext($e->getMessage(), ['guards' => $e->guards()]), 401);
        });

        $this->renderable(function (UnauthorizedException $e) {
            return response()->json(\App\Support\Api::failWithContext($e->getMessage(), request()->all()), 403);
        });

        $this->renderable(function (ValidationException $exception) {
            $errors = $exception->errors();
            $msg = (string) Arr::first(array_merge(...array_values($errors)));
            return response()->json(\App\Support\Api::failWithContext($msg, $errors));
        });

        $this->renderable(function (NotFoundHttpException $e) {
            if ($e->getPrevious() && $e->getPrevious() instanceof ModelNotFoundException) {
                $exception = $e->getPrevious();
                \App\Support\Logger::writeWithContext((string) sprintf("NotFoundHttpException: %s, trace: %s", $exception->getMessage(), $exception->getTraceAsString()), (string) 'error', (bool) false);
                return response()->json(\App\Support\Api::failWithContext($exception->getMessage(), request()->all()));
            }
        });
    }

    /**
     * Prepare a JSON response for the given exception.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Illuminate\Http\JsonResponse
     */
    protected function prepareJsonResponse($request, Throwable $e)
    {
        $data = $request->all();
        $httpStatusCode = $this->getHttpStatusCode($e);
        $msg = $e->getMessage() ?: class_basename($e);
        $trace = $e->getTraceAsString();
        if (config('app.debug')) {
            $data['trace'] = $trace;
        }
//        dd($e);
        if ($e instanceof \Error || $e instanceof \ErrorException) {
            \App\Support\Logger::writeWithContext((string) sprintf(get_class($e) . ": %s, trace: %s", $msg, $e->getTraceAsString()), (string) "error", (bool) false);
        }
        return new JsonResponse(
            \App\Support\Api::failWithContext($msg, $data),
            $httpStatusCode,
            $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getHeaders() : [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    protected function getHttpStatusCode(Throwable $e): int
    {
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        if (
            $e instanceof NexusException
            || $e instanceof \InvalidArgumentException
            || $e instanceof \LogicException
            || $e instanceof \RuntimeException
        ) {
            return 200;
        }

        return 500;
    }


}
