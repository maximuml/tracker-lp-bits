<?php

namespace App\Http\Controllers;

use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyController extends Controller
{
    public function bonus(Request $request): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || SupportContext::getUser() === null) {
            $qs = $request->getQueryString();
            return redirect('/mybonus.php' . ($qs ? '?' . $qs : ''));
        }

        return view('my.bonus');
    }

    public function hr(Request $request): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || SupportContext::getUser() === null) {
            $qs = $request->getQueryString();
            return redirect('/myhr.php' . ($qs ? '?' . $qs : ''));
        }

        return view('my.hr');
    }
}
