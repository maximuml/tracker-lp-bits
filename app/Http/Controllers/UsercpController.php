<?php

namespace App\Http\Controllers;

use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UsercpController extends LegacyController
{
    /**
     * Serve the legacy usercp.php page from a Laravel view.
     */
    public function legacy(Request $request): Response|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();
            return redirect('/usercp.php' . ($qs ? '?' . $qs : ''));
        }

        return $this->legacyPageRaw($request, 'usercp');
    }
}
