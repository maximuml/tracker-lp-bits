<?php

declare(strict_types=1);

namespace App\Services\Bonus\Exchanges;

use App\Support\Url;
use Illuminate\Http\RedirectResponse;

final class MybonusRedirect
{
    public static function to(string $baseUrl, string $do): RedirectResponse
    {
        return redirect(Url::absolute($baseUrl)."/web/mybonus?do={$do}");
    }
}
