<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var User $user */
        $user = $request->user();
        $user->checkIsNormal();

        return $next($request);
    }

    /**
     * 在响应发送到浏览器后处理任务。
     *
     * @param  mixed  $request
     * @param  mixed  $response
     * @return void
     */
    public function terminate($request, $response) {}
}
