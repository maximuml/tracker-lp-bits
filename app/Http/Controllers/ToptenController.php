<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\ToptenRepository;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class ToptenController extends Controller
{
    public function legacy(Request $request): Response|RedirectResponse
    {
        if (app(CurrentUser::class)->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/topten.php'.($qs ? '?'.$qs : ''));
        }

        if (! Permission::can(PermissionEnum::TOP_TEN)) {
            abort(403);
        }

        $type = (int) $request->query('type', 1);
        $limit = $request->has('lim') ? (int) $request->query('lim') : 10;
        $subtype = $request->query('subtype');
        $subtype = is_string($subtype) ? $subtype : null;

        $langFolder = (string) app(Globals::class)->get('CURLANGDIR', 'en');
        $cacheKey = "topten_{$type}_{$limit}_{$subtype}_{$langFolder}";

        $html = Cache::remember($cacheKey, 3600, function () use ($type, $limit, $subtype) {
            $page = ToptenRepository::page($type, $limit, $subtype);

            return view('topten.index', $page)->render();
        });

        return response($html);
    }
}
