<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function legacy(Request $request): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! isset($GLOBALS['CURUSER'])) {
            $qs = $request->getQueryString();
            return redirect('/log.php' . ($qs ? '?' . $qs : ''));
        }

        return view('log.index');
    }
}
