<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\ToptenRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Support\Locale;
use App\Support\RedisGuard;
use App\ViewModels\ToptenPageViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ToptenController extends Controller
{
    public function __construct(
        private readonly ActorContext $actor,
        private readonly ToptenRepositoryInterface $toptenRepository,
    ) {}

    public function legacy(Request $request): Response|RedirectResponse
    {
        if ($this->actor->isGuest()) {
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

        $vm = ToptenPageViewModel::fromPage($page, $this->toptenTabs());

        return response(view('topten.index', ['vm' => $vm])->render());
    }

    /**
     * @return list<array{id: string, label: string, url: string}>
     */
    private function toptenTabs(): array
    {
        $tabs = [];
        foreach ([1 => __('topten.text_users'), 2 => __('topten.text_torrents'), 3 => __('topten.text_countries'), 5 => __('topten.text_community'), 6 => __('topten.text_other')] as $navType => $label) {
            $tabs[] = ['id' => (string) $navType, 'label' => $label, 'url' => '/web/topten?type='.$navType];
        }

        return $tabs;
    }
}
