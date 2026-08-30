<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\TorrentApprovalStatus;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\TorrentBuyLog;
use App\Models\TorrentOperationLog;
use App\Models\User;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use App\Repositories\TorrentDetailRepository;
use App\Repositories\TorrentRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\CustomField;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Input;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Promotion;
use App\Support\Strings;
use App\Support\Torrent\BdInfoExtra;
use App\Support\Torrent\TechnicalInformation;
use App\Support\TorrentAccess;
use App\Support\TorrentBookmark;
use App\Support\UserDisplay;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TorrentDetailsController extends Controller
{
    private TorrentRepository $torrentRepository;

    private SearchBoxRepository $searchBoxRepository;

    private TagRepository $tagRepository;

    public function __construct(TorrentRepository $torrentRepository, SearchBoxRepository $searchBoxRepository, TagRepository $tagRepository)
    {
        $this->torrentRepository = $torrentRepository;
        $this->searchBoxRepository = $searchBoxRepository;
        $this->tagRepository = $tagRepository;
    }

    public function show(Request $request, int $id): View|RedirectResponse|Response
    {
        if ($id <= 0) {
            abort(404);
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $torrent = Torrent::query()->find($id);
        if (! $torrent instanceof Torrent) {
            abort(404);
        }

        Gate::forUser($user)->authorize('view', $torrent);

        if (app(LegacyRedisCache::class) === null) {
            $query = $request->query->all();
            unset($query['id']);

            return redirect('/details.php?id='.$id.($query ? '&'.http_build_query($query) : ''));
        }

        $row = TorrentDetailRepository::getTorrent($id);
        if (empty($row)) {
            Logger::writeWithContext((string) "TorrentDetailsRepository getTorrent empty: {$id}", (string) 'info', (bool) false);
            error_log("TorrentDetailsRepository getTorrent empty: $id");
            abort(404);
        }

        $currentUser = app(CurrentUser::class)->get() ?? $user->toLegacyArray();
        app(CurrentUser::class)->set($currentUser);

        if (empty(app(Globals::class)->get('lang_functions')) || empty(app(Globals::class)->get('lang_details'))) {
            Input::setServerValue('SCRIPT_NAME', '/details.php');
            require base_path(Locale::scriptFilePath((string) 'functions.php', (bool) false, (string) ''));
            app(Globals::class)->set('lang_functions', $lang_functions ?? []);
            require base_path(Locale::scriptFilePath((string) '', (bool) false, (string) ''));
            app(Globals::class)->set('lang_details', $lang_details ?? []);
        }

        $langDetails = app(Globals::class)->get('lang_details') ?? [];
        $headTitle = empty($request->input('cmtpage'))
            ? ($langDetails['head_details_for_torrent'] ?? '').'"'.$row['name'].'"'
            : ($langDetails['head_comments_for_torrent'] ?? '').'"'.$row['name'].'"';

        $denyLog = $row['approval_status'] == TorrentApprovalStatus::DENY->value
            ? TorrentDetailRepository::getLatestApprovalDenyLog($id)
            : null;

        $hasBuy = TorrentBuyLog::query()->where('uid', $currentUser['id'] ?? 0)->where('torrent_id', $id)->exists();

        $requestFlags = [
            'hit' => $request->has('hit'),
            'cmtpage' => $request->has('cmtpage'),
            'uploaded' => $request->has('uploaded'),
            'edited' => $request->has('edited'),
            'existed' => $request->has('existed'),
            'returnto' => (string) $request->input('returnto', ''),
            'dllist' => (int) $request->input('dllist', 0) === 1,
        ];

        if ($requestFlags['hit']) {
            TorrentDetailRepository::incrementViews($id);
        }

        $headers = [];
        if ($requestFlags['uploaded']) {
            $headers['Refresh'] = "1; url=download.php?id={$id}";
        }

        $tagIds = TorrentDetailRepository::getTagIds($id);

        $viewData = $this->buildDetailsViewData($id, $row, $currentUser, $user, $denyLog, $hasBuy, $tagIds, $requestFlags);

        return response()->view('torrent.details', array_merge([
            'torrentId' => $id,
            'torrentRow' => $row,
            'user' => $user,
            'currentUser' => $currentUser,
            'customField' => new CustomField,
            'headTitle' => $headTitle,
            'tagIds' => $tagIds,
            'denyLog' => $denyLog,
            'hasBuy' => $hasBuy,
            'requestFlags' => $requestFlags,
        ], $viewData), 200, $headers);
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @param  array<int|string, mixed>  $currentUser
     * @param  array<int, int>  $tagIds
     * @param  array<string, mixed>  $requestFlags
     * @return array<string, mixed>
     */
    private function buildDetailsViewData(int $id, array $row, array $currentUser, User $user, ?TorrentOperationLog $denyLog, bool $hasBuy, array $tagIds, array $requestFlags): array
    {
        $langFunctions = app(Globals::class)->get('lang_functions') ?? [];
        $langDetails = app(Globals::class)->get('lang_details') ?? [];

        $torrentRep = $this->torrentRepository;
        $searchBoxRep = $this->searchBoxRepository;
        $tagRep = $this->tagRepository;
        $customField = new CustomField;

        $bannedTorrent = ($row['banned'] ?? 0) == 1
            ? ' <b>(<font class="striking">'.($langFunctions['text_banned'] ?? '').'</font>)</b>'
            : '';

        $spTorrent = Promotion::appendWithContext(
            (int) $row['sp_state'], 'word', false, '', 0, '', $row['__ignore_global_sp_state'] ?? false
        );
        $spTorrentSub = Promotion::appendSubWithContext(
            (int) $row['sp_state'], '', true, $row['added'] ?? null,
            (int) ($row['promotion_time_type'] ?? 0),
            $row['promotion_until'] ?? null,
            $row['__ignore_global_sp_state'] ?? false
        );

        $torrentTopHtml = htmlspecialchars((string) $row['name'])
            .$bannedTorrent
            .$torrentRep->getPaidIcon($row, 20)
            .($spTorrent ? '&nbsp;&nbsp;&nbsp;'.$spTorrent : '')
            .$spTorrentSub
            .TorrentAccess::hrImage($row, (int) ($row['search_box_id'] ?? 0))
            .$torrentRep->renderApprovalStatus($row['approval_status'] ?? null);

        $editUrl = "edit.php?id={$id}";
        if ($requestFlags['returnto'] ?? '') {
            $editUrl .= '&returnto='.rawurlencode($requestFlags['returnto']);
        }

        $canViewAnonymous = Permission::can(PermissionEnum::VIEW_ANONYMOUS);
        $isOwner = (int) $currentUser['id'] === (int) ($row['owner'] ?? 0);
        if (($row['anonymous'] ?? 0) == 1) {
            if (! $canViewAnonymous && ! $isOwner) {
                $uprow = '<i>'.($langDetails['text_anonymous'] ?? '').'</i>';
            } else {
                $uprow = '<i>'.($langDetails['text_anonymous'] ?? '').'</i> ('.UserDisplay::username((int) ($row['owner'] ?? 0), false, true, true, false, false, true).')';
            }
        } else {
            $uprow = isset($row['owner'])
                ? UserDisplay::username((int) $row['owner'], false, true, true, false, false, true)
                : '<i>'.($langDetails['text_unknown'] ?? '').'</i>';
        }

        $bookmarkMarkup = TorrentBookmark::stateMarkupWithContext((int) $currentUser['id'], $id, false);
        $tagHtml = $tagRep->renderSpan((int) ($row['search_box_id'] ?? 0), $tagIds);

        $taxonomyInfo = $searchBoxRep->listTaxonomyInfo((int) ($row['search_box_id'] ?? 0), $row);
        $taxonomyRendered = '';
        foreach ($taxonomyInfo as $item) {
            $taxonomyRendered .= sprintf('&nbsp;&nbsp;&nbsp;<b>%s: </b>%s', $item['label'] ?? '', $item['value'] ?? '');
        }

        $downloadUrl = $torrentRep->getDownloadUrl($id, $currentUser);
        $customFieldsHtml = $customField->renderOnTorrentDetailsPage($id, (int) ($row['search_box_id'] ?? 0));

        $technicalInfoResult = null;
        if (SiteConfig::current()->main->enableTechnicalInfo() && ! empty($row['technical_info'])) {
            $escaped = Strings::escapeHtml((string) $row['technical_info']);
            $technicalData = is_string($escaped) ? $escaped : '';
            $isBdInfo = false;
            if (! empty($technicalData)) {
                $firstLine = (string) strtok($technicalData, "\n");
                if (
                    str_contains($firstLine, 'DISC INFO')
                    || str_contains($firstLine, 'Disc Title')
                    || str_contains($firstLine, 'Disc Label')
                ) {
                    $isBdInfo = true;
                }
            }

            if ($isBdInfo) {
                $technicalInfo = new BdInfoExtra($technicalData);
            } else {
                $technicalInfo = new TechnicalInformation($technicalData);
            }

            $technicalInfoResult = $technicalInfo->renderOnDetailsPage();
        }

        $descr = ! empty($row['descr']) ? Format::formatComment((string) $row['descr']) : '';
        $bonusOptions = Setting::getBonusRewardOptions();

        $magicInfo = TorrentDetailRepository::getMagicInfo($id, (int) $currentUser['id']);
        $thanksInfo = TorrentDetailRepository::getThanksInfo($id, (int) $currentUser['id']);

        $userIds = array_filter(array_unique([
            (int) ($row['owner'] ?? 0),
            (int) ($currentUser['id'] ?? 0),
        ]));
        foreach ($magicInfo['givers'] as $giver) {
            $userIds[] = (int) ($giver->userid ?? 0);
        }
        foreach ($thanksInfo['thanks'] as $t) {
            $userIds[] = (int) ($t->userid ?? 0);
        }
        $userIds = array_filter(array_unique($userIds));

        $userDisplayMap = [];
        foreach ($userIds as $uid) {
            $userDisplayMap[$uid] = UserDisplay::username($uid, false, true, true, false, false, true);
        }
        $currentUserHtml = UserDisplay::username((int) ($currentUser['id'] ?? 0), false, true, true, false, false, true);

        return [
            'torrentTopHtml' => $torrentTopHtml,
            'editUrl' => $editUrl,
            'uprow' => $uprow,
            'bookmarkMarkup' => $bookmarkMarkup,
            'tagHtml' => $tagHtml,
            'taxonomyRendered' => $taxonomyRendered,
            'downloadUrl' => $downloadUrl,
            'customFieldsHtml' => $customFieldsHtml,
            'technicalInfoResult' => $technicalInfoResult,
            'descr' => $descr,
            'bonusOptions' => $bonusOptions,
            'magicInfo' => $magicInfo,
            'thanksInfo' => $thanksInfo,
            'userDisplayMap' => $userDisplayMap,
            'currentUserHtml' => $currentUserHtml,
        ];
    }
}
