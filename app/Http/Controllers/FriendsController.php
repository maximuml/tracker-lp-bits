<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FriendsController extends LegacyController
{
    public function friends(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'friends');
    }
}
