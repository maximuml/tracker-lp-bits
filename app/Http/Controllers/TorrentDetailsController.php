<?php

namespace App\Http\Controllers;

use App\Models\Torrent;
use App\Models\TorrentBuyLog;
use App\Models\TorrentOperationLog;
use App\Models\User;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use App\Repositories\TorrentDetailRepository;
use App\Repositories\TorrentRepository;
use App\Support\Format;
use App\Support\Promotion;
use App\Models\Setting;
use App\Support\Strings;
use App\Support\SupportContext;
use App\Support\TorrentAccess;
use App\Support\TorrentBookmark;
use App\Support\UserDisplay;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Nexus\Field\Field;
use Nexus\Torrent\BdInfoExtra;
use Nexus\Torrent\TechnicalInformation;

class TorrentDetailsController extends Controller
{
    public function show(Request $request, int $id): View|RedirectResponse|Response
    {
        if ($id <= 0) {
            abort(404);
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto=' . urlencode($request->fullUrl()));
        }

        $torrent = Torrent::query()->find($id);
        if (! $torrent instanceof Torrent) {
            abort(404);
        }

        Gate::forUser($user)->authorize('view', $torrent);

        if (SupportContext::getCache() === null) {
            $query = $request->query->all();
            unset($query['id']);

            return redirect('/details.php?id=' . $id . ($query ? '&' . http_build_query($query) : ''));
        }

        $row = TorrentDetailRepository::getTorrent($id);
        if (empty($row)) {
            \App\Support\Logger::writeWithContext((string) "TorrentDetailsRepository getTorrent empty: {$id}", (string) 'info', (bool) false);
            error_log("TorrentDetailsRepository getTorrent empty: $id");
            abort(404);
        }

        $row = \App\Support\Hooks::applyFilter('torrent_detail', $row);

        $currentUser = SupportContext::getUser() ?? $user->toLegacyArray();
        SupportContext::setUser($currentUser);

        if (empty(SupportContext::getGlobal('lang_functions')) || empty(SupportContext::getGlobal('lang_details'))) {
            SupportContext::setServerValue('SCRIPT_NAME', '/details.php');
            require base_path(\App\Support\Locale::scriptFilePath((string) 'functions.php', (bool) false, (string) ""));
            SupportContext::setGlobal('lang_functions', $lang_functions ?? []);
            require base_path(\App\Support\Locale::scriptFilePath((string) "", (bool) false, (string) ""));
            SupportContext::setGlobal('lang_details', $lang_details ?? []);
        }

        $langDetails = SupportContext::getGlobal('lang_details') ?? [];
        $headTitle = empty($request->input('cmtpage'))
            ? ($langDetails['head_details_for_torrent'] ?? '') . '"' . $row['name'] . '"'
            : ($langDetails['head_comments_for_torrent'] ?? '') . '"' . $row['name'] . '"';

        $denyLog = $row['approval_status'] == Torrent::APPROVAL_STATUS_DENY
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
            'customField' => new Field(),
            'headTitle' => $headTitle,
            'tagIds' => $tagIds,
            'denyLog' => $denyLog,
            'hasBuy' => $hasBuy,
            'requestFlags' => $requestFlags,
        ], $viewData), 200, $headers);
    }

    /**
     * @param array<int|string, mixed> $row
     * @param array<int|string, mixed> $currentUser
     * @return array<string, mixed>
     */
    /**
     * @param array<int|string, mixed> $requestFlags
     */
    private function buildDetailsViewData(int $id, array $row, array $currentUser, User $user, ?TorrentOperationLog $denyLog, bool $hasBuy, array $tagIds, array $requestFlags): array
    {
        $langFunctions = SupportContext::getGlobal('lang_functions') ?? [];
        $langDetails = SupportContext::getGlobal('lang_details') ?? [];

        $torrentRep = new TorrentRepository();
        $searchBoxRep = new SearchBoxRepository();
        $tagRep = new TagRepository();
        $customField = new Field();

        $bannedTorrent = ($row['banned'] ?? '') === 'yes'
            ? " <b>(<font class=\"striking\">" . ($langFunctions['text_banned'] ?? '') . "</font>)</b>"
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
            . $bannedTorrent
            . $torrentRep->getPaidIcon($row, 20)
            . ($spTorrent ? '&nbsp;&nbsp;&nbsp;' . $spTorrent : '')
            . $spTorrentSub
            . TorrentAccess::hrImage($row, (int) ($row['search_box_id'] ?? 0))
            . $torrentRep->renderApprovalStatus($row['approval_status'] ?? null);

        $editUrl = "edit.php?id={$id}";
        if ($requestFlags['returnto'] ?? '') {
            $editUrl .= '&returnto=' . rawurlencode($requestFlags['returnto']);
        }

        $canViewAnonymous = \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_ANONYMOUS);
        $isOwner = (int) $currentUser['id'] === (int) ($row['owner'] ?? 0);
        if (($row['anonymous'] ?? '') === 'yes') {
            if (! $canViewAnonymous && ! $isOwner) {
                $uprow = '<i>' . ($langDetails['text_anonymous'] ?? '') . '</i>';
            } else {
                $uprow = '<i>' . ($langDetails['text_anonymous'] ?? '') . '</i> (' . UserDisplay::username((int) ($row['owner'] ?? 0), false, true, true, false, false, true) . ')';
            }
        } else {
            $uprow = isset($row['owner'])
                ? UserDisplay::username((int) $row['owner'], false, true, true, false, false, true)
                : '<i>' . ($langDetails['text_unknown'] ?? '') . '</i>';
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
        if (\App\Support\Config\SiteConfig::current()->main->enableTechnicalInfo() && ! empty($row['technical_info'])) {
            $technicalData = Strings::escapeHtml((string) $row['technical_info']);
            $isBdInfo = false;
            if (! empty($technicalData)) {
                $firstLine = strtok($technicalData, "\n");
                if (
                    strpos($firstLine, 'DISC INFO') !== false
                    || strpos($firstLine, 'Disc Title') !== false
                    || strpos($firstLine, 'Disc Label') !== false
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
