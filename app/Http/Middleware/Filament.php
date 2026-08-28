<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Url;
use Filament\Http\Middleware\Authenticate;

class Filament extends Authenticate
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  mixed  $request
     */
    protected function redirectTo($request): ?string
    {
        return Url::schemeAndHost(false).'/login.php';
    }
}
