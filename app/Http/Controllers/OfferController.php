<?php

namespace App\Http\Controllers;

use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OfferController extends LegacyController
{
    /**
     * Serve the legacy offers.php page from a Laravel view.
     */
    public function legacy(Request $request): Response|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            return redirect('/offers.php?' . $request->getQueryString());
        }

        return $this->legacyPageRaw($request, 'offers');
    }
}
