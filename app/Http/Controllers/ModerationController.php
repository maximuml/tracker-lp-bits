<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ModerationController extends LegacyController
{
    public function report(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'report');

    }

    public function reports(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'reports');

    }

    public function bans(Request $request): View|RedirectResponse|Response
    {

        if ($request->isMethod('post')) {
            return $this->legacyPageWithRedirect($request, 'bans');
        }

        return $this->legacyPage($request, 'bans');

    }

    public function cheaterbox(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'cheaterbox');

    }

    public function cheaters(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'cheaters');

    }

    public function iphistory(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'iphistory');

    }

    public function ipcheck(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'ipcheck');

    }

    public function ipsearch(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'ipsearch');

    }

}