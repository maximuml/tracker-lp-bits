<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ToptenController extends Controller
{
    public function legacy(Request $request): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! isset($GLOBALS['CURUSER'])) {
            $qs = $request->getQueryString();
            return redirect('/topten.php' . ($qs ? '?' . $qs : ''));
        }

        return view('topten.index');
    }
}
