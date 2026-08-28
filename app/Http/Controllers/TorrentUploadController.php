<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Models\Offer;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UploadRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Category;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Nexus\Field\Field;

class TorrentUploadController extends Controller
{
    private TorrentRepository $torrentRepository;

    private SearchBoxRepository $searchBoxRepository;

    private TagRepository $tagRepository;

    private HitAndRunRepository $hitAndRunRepository;

    public function __construct(TorrentRepository $torrentRepository, SearchBoxRepository $searchBoxRepository, TagRepository $tagRepository, HitAndRunRepository $hitAndRunRepository)
    {
        $this->torrentRepository = $torrentRepository;
        $this->searchBoxRepository = $searchBoxRepository;
        $this->tagRepository = $tagRepository;
        $this->hitAndRunRepository = $hitAndRunRepository;
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (app(LegacyRedisCache::class) === null) {
            return redirect('/upload.php?'.$request->getQueryString());
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = app(CurrentUser::class)->get() ?? $user->toLegacyArray();
        app(CurrentUser::class)->set($currentUser);

        if (empty(app(Globals::class)->get('lang_upload')) || empty(app(Globals::class)->get('lang_edit'))) {
            Input::setServerValue('SCRIPT_NAME', '/upload.php');
            require base_path(Locale::scriptFilePath((string) '', (bool) false, (string) ''));
            app(Globals::class)->set('lang_upload', $lang_upload ?? []);
            require base_path(Locale::scriptFilePath((string) 'edit.php', (bool) false, (string) ''));
            app(Globals::class)->set('lang_edit', $lang_edit ?? []);
        }

        /** @var array<string, string> $lang_upload */
        $lang_upload = app(Globals::class)->get('lang_upload') ?? [];
        /** @var array<string, string> $lang_edit */
        $lang_edit = app(Globals::class)->get('lang_edit') ?? [];

        if ($currentUser['parked'] === 'yes') {
            LegacyResponse::abort($lang_upload['std_sorry'] ?? '', $lang_upload['std_unauthorized_to_upload'] ?? '', false);
        }

        if ($currentUser['uploadpos'] == 'no') {
            LegacyResponse::abort($lang_upload['std_sorry'] ?? '', $lang_upload['std_unauthorized_to_upload'] ?? '', false);
        }

        $enableoffer = app(Globals::class)->get('enableoffer', 'no');
        $has_allowed_offer = 0;
        $offerRows = [];
        if ($enableoffer === 'yes') {
            $offerRows = Offer::query()
                ->where('allowed', 'allowed')
                ->where('userid', $currentUser['id'])
                ->orderBy('name')
                ->get()
                ->toArray();
            $has_allowed_offer = count($offerRows);
        }

        $uploadFreely = LegacyResponse::canUpload('torrents');
        $allowtorrents = $has_allowed_offer || $uploadFreely;
        if (! $allowtorrents) {
            LegacyResponse::abort($lang_upload['std_sorry'] ?? '', $lang_upload['std_please_offer'] ?? '', false);
        }

        $browsecatmode = (int) (app(Globals::class)->get('browsecatmode') ?? 1);

        return view('torrents.upload', [
            'uploadFreely' => $uploadFreely,
            'allowtorrents' => $allowtorrents,
            'offerRows' => $offerRows,
            'torrentRep' => $this->torrentRepository,
            'searchBoxRep' => $this->searchBoxRepository,
            'tagRep' => $this->tagRepository,
            'customField' => new Field,
            'hitAndRunRep' => $this->hitAndRunRepository,
            'pageTitle' => $lang_upload['head_upload'] ?? '',
            'cats' => Category::listByModeWithContext($browsecatmode),
        ]);
    }

    public function legacyStore(Request $request, UploadRepository $repository): RedirectResponse
    {
        try {
            $torrent = $repository->upload($request);
        } catch (TorrentAlreadyExistsException $e) {
            return redirect('details.php?id='.$e->getTorrentId().'&existed=1');
        }

        return redirect('details.php?id='.$torrent->id.'&uploaded=1');
    }
}
