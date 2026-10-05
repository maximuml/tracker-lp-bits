<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\TorrentAjaxRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Models\Torrent;
use App\Repositories\TorrentModerationRepository;
use App\Services\PermissionChecker;
use App\Support\Category;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\LegacyYesNo;
use App\Support\Locale;
use App\Support\Promotion;
use App\Support\Ratio;
use App\Support\Strings;
use App\Support\Time;
use App\Support\Torrent\FileBadge;
use App\Support\TorrentAccess;
use App\Support\UserDisplay;
use App\ViewModels\Torrent\ApprovalBadge;
use App\ViewModels\Torrent\CategoryIcon;
use App\ViewModels\Torrent\PeerTableFactory;
use App\ViewModels\Torrent\TorrentBadgeSet;
use App\ViewModels\Torrent\UserTorrentListViewModel;
use App\ViewModels\Torrent\UserTorrentRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TorrentAjaxController extends LegacyController
{
    public function __construct(private readonly PermissionChecker $permissionChecker, private readonly UserRepositoryInterface $userRepository,
        protected CurrentUser $currentUser,
        protected TorrentAjaxRepositoryInterface $torrentAjaxRepository,
        protected PeerTableFactory $peerTableFactory,
    ) {}

    public function viewFileList(Request $request): Response|RedirectResponse
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return response('', 400, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $files = $this->torrentAjaxRepository->fileList($torrentId)
            ->map(fn ($fileRow): array => [
                'badge' => FileBadge::forFilename((string) (((array) $fileRow)['filename'] ?? '')),
                'filename' => (string) (((array) $fileRow)['filename'] ?? ''),
                'size' => Format::size((float) (((array) $fileRow)['size'] ?? 0)),
            ])
            ->all();

        return response()->view('viewfilelist.index', ['files' => $files], 200, [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    public function viewPeerList(Request $request): Response|RedirectResponse
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return response('', 400, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $curUser = $this->currentUser->get() ?? [];
        $currentUser = ! empty($curUser) ? $this->userRepository->findById((int) ($curUser['id'] ?? 0)) : null;

        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/html; charset=utf-8',
        ];

        $data = $this->torrentAjaxRepository->peerList($torrentId, $currentUser);
        $curUserArr = $curUser;

        $data['seederTable'] = $this->peerTableFactory->buildTable((string) (__('legacy/viewpeerlist.text_seeders')), $data['seeders'], $data['torrent'], $data['privacyData'], $data['showLocationColumn'], $data['enablelocationTweak'], $data['peerIpInfo'], $data['usernameHtmlMap'], $curUserArr);
        $data['leecherTable'] = $this->peerTableFactory->buildTable((string) (__('legacy/viewpeerlist.text_leechers')), $data['leechers'], $data['torrent'], $data['privacyData'], $data['showLocationColumn'], $data['enablelocationTweak'], $data['peerIpInfo'], $data['usernameHtmlMap'], $curUserArr);

        return response()->view('viewpeerlist.index', $data, 200, $headers);
    }

    public function viewSnatches(Request $request): View|RedirectResponse|Response
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return redirect('/torrents.php');
        }

        $data = $this->torrentAjaxRepository->snatchList($torrentId);
        $curUser = $this->currentUser->get() ?? [];
        $data['rows'] = $this->decorateSnatchRows(
            $data['snatchedRows'] ?? collect(),
            (int) ($curUser['id'] ?? 0)
        );
        $data['canViewConfidential'] = Permission::can(PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO);
        unset($data['snatchedRows']);

        return $this->legacyPage($request, 'viewsnatches', true, $data);
    }

    /**
     * @param  iterable<int, mixed>  $snatchedRows
     * @return list<array<string, mixed>>
     */
    private function decorateSnatchRows(iterable $snatchedRows, int $currentUserId): array
    {
        $perSecond = (string) (__('legacy/viewsnatches.text_per_second'));
        $snatchedRows = collect($snatchedRows);
        UserDisplay::preload($snatchedRows->pluck('userid')->map(fn ($id) => (int) $id)->all());
        $rows = [];
        foreach ($snatchedRows as $snatchRow) {
            $arr = (array) $snatchRow;
            $ratioText = '---';
            $ratioClass = null;
            if ($arr['downloaded'] > 0) {
                $ratioText = number_format($arr['uploaded'] / $arr['downloaded'], 3);
                $ratioClass = Ratio::colorClass($ratioText);
            } elseif ($arr['uploaded'] > 0) {
                $ratioText = (string) (__('legacy/viewsnatches.text_inf'));
            }
            $uprate = $arr['seedtime'] > 0
                ? Format::size($arr['uploaded'] / ($arr['seedtime'] + $arr['leechtime']))
                : Format::size(0);
            $downrate = $arr['leechtime'] > 0
                ? Format::size($arr['downloaded'] / $arr['leechtime'])
                : Format::size(0);

            $userrow = UserDisplay::row($arr['userid']);
            $privacy = is_array($userrow) ? (string) ($userrow['privacy'] ?? '') : '';
            $anonymous = $privacy == 'strong';
            $revealName = $anonymous
                && (Permission::can(PermissionEnum::VIEW_ANONYMOUS) || $arr['id'] == $currentUserId);

            $rows[] = [
                'highlight' => $currentUserId == $arr['userid'],
                'anonymous' => $anonymous,
                'revealName' => $revealName,
                'name' => UserDisplay::username($arr['userid']),
                'ip' => (string) ($arr['ip'] ?? ''),
                'trafficUp' => Format::size((float) $arr['uploaded']).'@'.$uprate.$perSecond,
                'trafficDown' => Format::size((float) $arr['downloaded']).'@'.$downrate.$perSecond,
                'ratioText' => $ratioText,
                'ratioClass' => $ratioClass,
                'seedtime' => Format::prettyTimeWithLocale((float) $arr['seedtime']),
                'leechtime' => Format::prettyTimeWithLocale((float) $arr['leechtime']),
                'completedAt' => SafeHtml::fromTrustedHtml((string) Time::format($arr['completedat'], true, false)),
                'lastAction' => SafeHtml::fromTrustedHtml((string) Time::format($arr['last_action'], true, false)),
                'reportUserId' => (int) $arr['userid'],
                'reportLinked' => $privacy != 'strong' || Permission::can(PermissionEnum::VIEW_ANONYMOUS),
            ];
        }

        return $rows;
    }

    public function getUserTorrentListAjax(Request $request): Response|RedirectResponse
    {
        $targetUserId = (int) $request->input('userid', 0);
        $type = (string) $request->input('type', '');

        if ($targetUserId <= 0 || ! in_array($type, ['uploaded', 'seeding', 'leeching', 'completed', 'incomplete'], true)) {
            return response('', 400, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $curUser = $this->currentUser->get() ?? [];
        $currentUser = ! empty($curUser) ? $this->userRepository->findById((int) ($curUser['id'] ?? 0)) : null;

        if ($currentUser === null || (! $this->permissionChecker->userCan(PermissionEnum::TORRENT_HISTORY->value, false, $currentUser->id) && $currentUser->id !== $targetUserId)) {
            return response('', 403, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $page = (int) $request->input('page', 0);

        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/html; charset=utf-8',
        ];

        $data = $this->torrentAjaxRepository->userTorrentList($targetUserId, $type, $page, $currentUser);

        $data['userTorrentListVm'] = ($data['count'] > 0 && ! empty($data['rows']))
            ? $this->userTorrentListVm($data['rows'], $type, $targetUserId, $curUser, $data['seedTimeAndUploaded'], $data['torrentRep'])
            : null;

        $hasData = (bool) ($data['total_size'] || $data['count']);
        $summaryText = (__('legacy/getusertorrentlistajax.text_record')).Strings::addS($data['count']);
        if ($data['total_size']) {
            $summaryText .= (__('legacy/getusertorrentlistajax.text_total_size')).Format::size((float) $data['total_size']);
        }

        $data['hasData'] = $hasData;
        $data['summaryCount'] = (int) $data['count'];
        $data['summaryText'] = $summaryText;

        return response()->view('getusertorrentlistajax.index', $data, 200, $headers);
    }

    /**
     * @param  iterable<int, mixed>  $rows
     * @param  array<string, mixed>  $currentUser
     */
    private function userTorrentListVm(iterable $rows, string $mode, int $id, array $currentUser, mixed $seedTimeAndUploaded, TorrentModerationRepository $torrentRep): UserTorrentListViewModel
    {
        $showsize = $showsenum = $showlenum = $showuploaded = $showdownloaded = $showratio = $showsetime = $showletime = $showcotime = $showanonymous = false;
        $showClient = false;
        switch ($mode) {
            case 'uploaded':
                $showsize = $showsenum = $showlenum = $showuploaded = $showsetime = $showanonymous = true;
                break;
            case 'seeding':
                $showsize = $showsenum = $showlenum = $showuploaded = $showdownloaded = $showratio = $showsetime = true;
                $showClient = true;
                break;
            case 'leeching':
                $showsize = $showsenum = $showlenum = $showuploaded = $showdownloaded = $showratio = true;
                $showClient = true;
                break;
            case 'completed':
                $showsize = $showuploaded = $showsetime = $showletime = $showcotime = true;
                break;
            case 'incomplete':
                $showsize = $showuploaded = $showdownloaded = $showratio = $showletime = true;
                break;
        }

        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $shouldShowClient = $showClient
            && ($this->permissionChecker->userCan(PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO->value, false, $currentUserId) || $currentUserId == $id);
        $maxNameLength = ($currentUser['fontsize'] ?? null) == 'large' ? 70 : 80;

        $vmRows = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            if ($mode === 'uploaded') {
                $seedTimeAndUploadedData = $seedTimeAndUploaded->get($arr['torrent']);
                $arr['seedtime'] = $seedTimeAndUploadedData ? $seedTimeAndUploadedData->seedtime : 0;
                $arr['uploaded'] = $seedTimeAndUploadedData ? $seedTimeAndUploadedData->uploaded : 0;
            }

            $nameTitle = trim((string) $arr['torrentname']);
            $displayName = $nameTitle;
            if (mb_strlen($displayName, 'UTF-8') > $maxNameLength) {
                $displayName = mb_substr($displayName, 0, $maxNameLength, 'UTF-8').'..';
            }

            $uploadedBytes = (float) ($arr['uploaded'] ?? 0);
            $downloadedBytes = (float) ($arr['downloaded'] ?? 0);
            if ($downloadedBytes > 0) {
                $ratioText = number_format($uploadedBytes / $downloadedBytes, 3);
                $ratioClass = Ratio::colorClass($ratioText) ?: null;
            } elseif ($uploadedBytes > 0) {
                $ratioText = 'Inf.';
                $ratioClass = null;
            } else {
                $ratioText = '---';
                $ratioClass = null;
            }

            $added = (string) ($arr['added'] ?? '');
            $categoryIcon = null;
            if (isset($arr['category'])) {
                $catData = Category::iconData($arr['category']);
                $categoryIcon = new CategoryIcon($catData['iconClass'], $catData['name'], 'torrents.php?allsec=1&cat='.$arr['category']);
            }

            $vmRows[] = new UserTorrentRow(
                rowClass: Promotion::rowClassWithContext((int) $arr['sp_state'], '', $arr),
                categoryIcon: $categoryIcon,
                nameUrl: 'details.php?id='.$arr['torrent'].'&hit=1',
                nameTitle: $nameTitle,
                displayName: $displayName,
                isBanned: LegacyYesNo::isYes($arr['banned'] ?? null),
                badges: new TorrentBadgeSet(
                    promotion: Promotion::badgeWithContext((int) $arr['sp_state'], '', false, '', 0, '', $arr['__ignore_global_sp_state'] ?? false),
                    hitAndRun: TorrentAccess::requiresHrIcon($arr, $arr['search_box_id'] ?? 0),
                    approval: $torrentRep->shouldShowApprovalStatusIcon($arr['approval_status'])
                        ? new ApprovalBadge(
                            title: (string) Locale::trans("torrent.approval.status_text.{$arr['approval_status']}", [], null),
                            icon: Torrent::approvalStatusIcon((int) $arr['approval_status']),
                        )
                        : null,
                ),
                addedDate: substr($added, 0, 10),
                addedTime: substr($added, 11),
                size: Format::sizeParts((float) ($arr['size'] ?? 0)),
                seeders: (int) ($arr['seeders'] ?? 0),
                leechers: (int) ($arr['leechers'] ?? 0),
                uploaded: Format::sizeParts($uploadedBytes),
                downloaded: Format::sizeParts($downloadedBytes),
                ratioText: $ratioText,
                ratioClass: $ratioClass,
                seedTime: Format::prettyTimeWithLocale((float) ($arr['seedtime'] ?? 0)),
                leechTime: Format::prettyTimeWithLocale((float) ($arr['leechtime'] ?? 0)),
                completedAt: $arr['completedat'] ?? null,
                anonymous: (string) ($arr['anonymous'] ?? ''),
                clientAgent: Strings::userAgentClient((string) ($arr['agent'] ?? '')),
                clientPort: (string) ($arr['port'] ?? ''),
                clientIps: array_values(array_filter([(string) ($arr['ipv4'] ?? ''), (string) ($arr['ipv6'] ?? '')])),
            );
        }

        return new UserTorrentListViewModel(
            rows: $vmRows,
            showSize: $showsize,
            showSeeders: $showsenum,
            showLeechers: $showlenum,
            showUploaded: $showuploaded,
            showDownloaded: $showdownloaded,
            showRatio: $showratio,
            showSeedTime: $showsetime,
            showLeechTime: $showletime,
            showCompletedAt: $showcotime,
            showAnonymous: $showanonymous,
            showClient: $shouldShowClient,
        );
    }

    /**
     * Maps a file extension to a category used for badge color.
     */
    public function searchSuggest(Request $request): Response|RedirectResponse
    {
        $searchstr = (string) $request->input('q', '');
        if ($searchstr === '') {
            return response((string) json_encode([], JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        return response(
            (string) json_encode($this->torrentAjaxRepository->searchSuggest($searchstr), JSON_UNESCAPED_UNICODE),
            200,
            ['Content-Type' => 'application/x-suggestions+json; charset=utf-8']
        );
    }

    public function autocompleteTorrents(Request $request): Response|RedirectResponse|JsonResponse
    {
        $query = (string) $request->input('q', '');
        if ($query === '') {
            return response()->json(['torrents' => []]);
        }

        $userId = (int) ($this->currentUser->get()['id'] ?? 0);
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            return response()->json(['torrents' => []]);
        }

        return response()->json($this->torrentAjaxRepository->autocompleteTorrents($query, $user));
    }
}
