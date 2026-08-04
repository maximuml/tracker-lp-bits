<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends Controller
{
    /**
     * Serve the legacy offers.php page from a Laravel view.
     *
     * The real markup lives in resources/views/offers/_offers_legacy.php
     * and is included by offers/index.blade.php so the original HTML/PHP
     * interleaving is preserved as closely as possible.
     */
    public function legacy(Request $request): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! isset($GLOBALS['CURUSER'])) {
            return redirect('/offers.php?' . $request->getQueryString());
        }

        return view('offers.index');
    }
}
