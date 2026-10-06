<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\ToptenRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Support\CurrentUser;
use App\Support\Locale;
use App\Support\RedisGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ToptenController extends Controller
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly ToptenRepositoryInterface $toptenRepository,
    ) {}

    public function legacy(Request $request): Response|RedirectResponse
    {
        if ($this->currentUser->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/web/topten'.($qs ? '?'.$qs : ''));
        }

        if (! Permission::can(PermissionEnum::TOP_TEN)) {
            abort(403);
        }

        $type = (int) $request->query('type', 1);
        $limit = $request->has('lim') ? (int) $request->query('lim') : 10;
        $subtype = $request->query('subtype');
        $subtype = is_string($subtype) ? $subtype : null;

        $langFolder = Locale::currentLangDir('en');
        $cacheKey = "topten_data_{$type}_{$limit}_{$subtype}_{$langFolder}";

        $page = RedisGuard::remember($cacheKey, 3600, function () use ($type, $limit, $subtype) {
            $page = $this->toptenRepository->page($type, $limit, $subtype);
            $page['generatedAt'] = date('Y-m-d H:i:s');

            return $page;
        });

        return response(view('topten.index', $page)->render());
    }
}
