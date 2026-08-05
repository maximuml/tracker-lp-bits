<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegacyPagesController extends Controller
{
    public function friends(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'friends');
    }

    public function messages(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'messages');
    }

    public function getrss(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'getrss');
    }

    public function sendmessage(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'sendmessage');
    }

    public function userhistory(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'userhistory');
    }

    public function invite(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'invite');
    }

    private function legacy(Request $request, string $page): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! isset($GLOBALS['CURUSER'])) {
            $qs = $request->getQueryString();
            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        return view($page . '.index');
    }
}
