<?php

namespace App\Http\Controllers;

use App\Models\Torrent;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use App\Repositories\TorrentDetailRepository;
use App\Repositories\TorrentEditRepository;
use App\Support\Category;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Locale;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Nexus\Field\Field;

class TorrentEditController extends Controller
{
    public function legacy(Request $request): View|RedirectResponse
    {
        if (app(CurrentUser::class)->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/edit.php'.($qs ? '?'.$qs : ''));
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            abort(404);
        }

        $torrent = Torrent::query()->find($id);
        if (! $torrent instanceof Torrent) {
            abort(404);
        }

        $row = TorrentDetailRepository::getTorrent($id);
        if (empty($row)) {
            abort(404);
        }
        $sectionmode = (int) ($row['search_box_id'] ?? 0);
        $row['cat_mode'] = $sectionmode;

        $user = Auth::guard('nexus-web')->user();
        if ($user === null) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        if (empty(app(Globals::class)->get('lang_edit')) || empty(app(Globals::class)->get('lang_functions'))) {
            Input::setServerValue('SCRIPT_NAME', '/edit.php');
            require base_path(Locale::scriptFilePath((string) 'functions.php', (bool) false, (string) ''));
            app(Globals::class)->set('lang_functions', $lang_functions ?? []);
            require base_path(Locale::scriptFilePath((string) '', (bool) false, (string) ''));
            app(Globals::class)->set('lang_edit', $lang_edit ?? []);
        }

        $currentUser = app(CurrentUser::class)->get();
        app(CurrentUser::class)->set($currentUser);

        $langEdit = app(Globals::class)->get('lang_edit') ?? [];
        $headTitle = ($langEdit['head_edit_torrent'] ?? '').'"'.$row['name'].'"';

        return view('torrent.edit', [
            'torrentId' => $id,
            'torrentRow' => $row,
            'currentUser' => $currentUser,
            'headTitle' => $headTitle,
            'tagIds' => TorrentDetailRepository::getTagIds($id),
            'cats' => Category::listByModeWithContext($sectionmode),
            'returnto' => (string) $request->input('returnto', ''),
            'requestUri' => is_string($request->server('REQUEST_URI')) ? $request->server('REQUEST_URI') : '',
            'taxonomySelect' => (new SearchBoxRepository)->renderTaxonomySelect($sectionmode, $row),
            'tagCheckbox' => (new TagRepository)->renderCheckbox($sectionmode, (array) TorrentDetailRepository::getTagIds($id)),
            'customFieldsHtml' => (new Field)->renderOnUploadPage($id, $sectionmode),
            'hitAndRunHtml' => (new HitAndRunRepository)->renderOnUploadPage($row['hr'] ?? 0, $sectionmode),
        ]);
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
        if (! empty($parsed['scheme']) || ! empty($parsed['host']) || str_starts_with($returl, '//')) {
            return $defaultUrl;
        }

        return $returl;
    }
}
