<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FriendsController extends LegacyController
{
    public function friends(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'friends');
    }
}
