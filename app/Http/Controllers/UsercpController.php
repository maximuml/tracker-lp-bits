<?php

namespace App\Http\Controllers;

use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsercpController extends Controller
{
    /**
     * Serve the legacy usercp.php page from a Laravel view.
     *
     * The real markup lives in resources/views/usercp/_usercp_legacy.php
     * and is included by usercp/index.blade.php so the original HTML/PHP
     * interleaving is preserved as closely as possible.
     */
    public function legacy(Request $request): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! IN_NEXUS || SupportContext::getUser() === null) {
            $qs = $request->getQueryString();
            return redirect('/usercp.php' . ($qs ? '?' . $qs : ''));
        }

        return view('usercp.index');
    }
}
