<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SupportController extends LegacyController
{
    public function complains(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'complains', false);

    }

}