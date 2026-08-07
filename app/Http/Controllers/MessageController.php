<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MessageController extends LegacyController
{
    public function messages(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'messages');
    }

    public function sendmessage(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'sendmessage');
    }

    public function takeMessage(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'takemessage');
    }

    public function deletemessage(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'deletemessage');
    }
}
