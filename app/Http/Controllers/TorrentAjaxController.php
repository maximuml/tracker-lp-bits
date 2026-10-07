<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\TorrentAjaxRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Services\PermissionChecker;
use App\Support\Category;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Ratio;
use App\Support\Strings;
use App\Support\Time;
use App\Support\Torrent\FileBadge;
use App\Support\UserDisplay;
use App\ViewModels\Torrent\PeerTableFactory;
use App\ViewModels\Torrent\UserTorrentListVmFactory;
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
        protected UserTorrentListVmFactory $userTorrentListVmFactory,
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
        $currentUser = ! empty($curUser) ? $this->userRepository->findById((int) ($this->currentUser->id())) : null;

        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/html; charset=utf-8',
        ];

        $data = $this->torrentAjaxRepository->peerList($torrentId, $currentUser);
        $curUserArr = $curUser;

        $data['seederTable'] = $this->peerTableFactory->buildTable((string) (__('viewpeerlist.text_seeders')), $data['seeders'], $data['torrent'], $data['privacyData'], $data['showLocationColumn'], $data['enablelocationTweak'], $data['peerIpInfo'], $data['usernameHtmlMap'], $curUserArr);
        $data['leecherTable'] = $this->peerTableFactory->buildTable((string) (__('viewpeerlist.text_leechers')), $data['leechers'], $data['torrent'], $data['privacyData'], $data['showLocationColumn'], $data['enablelocationTweak'], $data['peerIpInfo'], $data['usernameHtmlMap'], $curUserArr);

        return response()->view('viewpeerlist.index', $data, 200, $headers);
    }

    public function viewSnatches(Request $request): View|RedirectResponse|Response
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return redirect('/web/torrents');
        }

        $data = $this->torrentAjaxRepository->snatchList($torrentId);
        $curUser = $this->currentUser->get() ?? [];
        $data['rows'] = $this->decorateSnatchRows(
            $data['snatchedRows'] ?? collect(),
            (int) ($this->currentUser->id())
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
        $perSecond = (string) (__('viewsnatches.text_per_second'));
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
                $ratioText = (string) (__('viewsnatches.text_inf'));
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
        $currentUser = ! empty($curUser) ? $this->userRepository->findById((int) ($this->currentUser->id())) : null;

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
            ? $this->userTorrentListVmFactory->build($data['rows'], $type, $targetUserId, $curUser, $data['seedTimeAndUploaded'], $data['torrentRep'])
            : null;

        $hasData = (bool) ($data['total_size'] || $data['count']);
        $summaryText = (__('getusertorrentlistajax.text_record')).Strings::addS($data['count']);
        if ($data['total_size']) {
            $summaryText .= (__('getusertorrentlistajax.text_total_size')).Format::size((float) $data['total_size']);
        }

        $data['hasData'] = $hasData;
        $data['summaryCount'] = (int) $data['count'];
        $data['summaryText'] = $summaryText;

        return response()->view('getusertorrentlistajax.index', $data, 200, $headers);
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
