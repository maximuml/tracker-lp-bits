<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MyController extends Controller
{
    public function bonus(Request $request): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! isset($GLOBALS['CURUSER'])) {
            $qs = $request->getQueryString();
            return redirect('/mybonus.php' . ($qs ? '?' . $qs : ''));
        }

        return view('my.bonus');
    }

    public function hr(Request $request): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! isset($GLOBALS['CURUSER'])) {
            $qs = $request->getQueryString();
            return redirect('/myhr.php' . ($qs ? '?' . $qs : ''));
        }

        return view('my.hr');
    }

    public function bar(Request $request): Response|RedirectResponse
    {
        if (! defined('IN_NEXUS')) {
            $qs = $request->getQueryString();
            return redirect('/mybar.php' . ($qs ? '?' . $qs : ''));
        }

        $content = view('my.bar')->render();

        return response($content, 200, ['Content-Type' => 'image/png']);
    }
}
