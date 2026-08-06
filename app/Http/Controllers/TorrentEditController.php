<?php

namespace App\Http\Controllers;

use App\Repositories\TorrentEditRepository;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TorrentEditController extends Controller
{
    public function legacy(Request $request): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || SupportContext::getUser() === null) {
            $qs = $request->getQueryString();
            return redirect('/edit.php' . ($qs ? '?' . $qs : ''));
        }

        return view('torrent.edit');
    }

    public function legacyUpdate(Request $request, TorrentEditRepository $repository): RedirectResponse
    {
        $torrent = $repository->update($request);

        $id = $torrent->id;
        $defaultUrl = "details.php?id=$id&edited=1";
        $returl = $request->input('returnto', $defaultUrl);

        return redirect($this->safeReturnUrl($returl, $defaultUrl));
    }

    private function safeReturnUrl(string $returl, string $defaultUrl): string
    {
        $returl = trim($returl);
        if ($returl === '') {
            return $defaultUrl;
        }

        $parsed = parse_url($returl);
        if (!empty($parsed['scheme']) || !empty($parsed['host']) || str_starts_with($returl, '//')) {
            return $defaultUrl;
        }

        return $returl;
    }
}
