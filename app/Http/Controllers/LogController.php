<?php

namespace App\Http\Controllers;

use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogController extends LegacyController
{
    public function legacy(Request $request): Response|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();
            return redirect('/log.php' . ($qs ? '?' . $qs : ''));
        }

        return $this->legacyPageRaw($request, 'log');
    }
}
