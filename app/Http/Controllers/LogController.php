<?php

namespace App\Http\Controllers;

use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function legacy(Request $request): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || SupportContext::getUser() === null) {
            $qs = $request->getQueryString();
            return redirect('/log.php' . ($qs ? '?' . $qs : ''));
        }

        return view('log.index');
    }
}
