<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\TorrentAjaxRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Support\Category;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Html\SafeHtml;
use App\Support\LegacyYesNo;
use App\Support\Permissions;
use App\Support\Promotion;
use App\Support\Ratio;
use App\Support\Strings;
use App\Support\Time;
use App\Support\TorrentAccess;
use App\Support\UserDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TorrentAjaxController extends LegacyController
{
    public function __construct(
        protected Globals $globals,
        protected CurrentUser $currentUser,
        protected TorrentAjaxRepositoryInterface $torrentAjaxRepository,
    ) {}

    public function viewFileList(Request $request): Response|RedirectResponse
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return response('', 400, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $files = $this->torrentAjaxRepository->fileList($torrentId)
            ->map(fn ($fileRow): array => [
                'badgeHtml' => SafeHtml::fromTrustedHtml($this->fileBadge((string) (((array) $fileRow)['filename'] ?? ''))),
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
        $currentUser = ! empty($curUser) ? User::query()->find((int) ($curUser['id'] ?? 0)) : null;

        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/html; charset=utf-8',
        ];

        $data = $this->torrentAjaxRepository->peerList($torrentId, $currentUser);
        $curUserArr = $curUser;

        $data['seederTableHtml'] = SafeHtml::fromTrustedHtml($this->peerTable((string) (__('legacy/viewpeerlist.text_seeders')), $data['seeders'], $data['torrent'], $data['privacyData'], $data['showLocationColumn'], $data['enablelocationTweak'], $data['peerIpInfo'], $data['usernameHtmlMap'], $curUserArr));
        $data['leecherTableHtml'] = SafeHtml::fromTrustedHtml($this->peerTable((string) (__('legacy/viewpeerlist.text_leechers')), $data['leechers'], $data['torrent'], $data['privacyData'], $data['showLocationColumn'], $data['enablelocationTweak'], $data['peerIpInfo'], $data['usernameHtmlMap'], $curUserArr));

        return response()->view('viewpeerlist.index', $data, 200, $headers);
    }

    /**
     * @param  array<string, mixed>  $e
     * @param  array<int, list<array<string, string>>>  $peerIpInfo
     */
    private function peerLocationColumn(array $e, bool $isStrongPrivacy, bool $canView, mixed $enablelocationTweak, array $peerIpInfo): string
    {
        $address = $ips = [];
        $info = $peerIpInfo[$e['id']] ?? [];

        if ($enablelocationTweak === 'yes') {
            foreach ($info as $ipInfo) {
                $address[] = $ipInfo['public'];
                $ips[] = $ipInfo['ip'];
            }
            $title = $canView ? sprintf('%s%s%s', __('legacy/functions.text_user_ip'), ':&nbsp;', implode(', ', $ips)) : '';
            $addressStr = implode('<br/>', $address);
            $location = '<div title="'.$title.'">'.$addressStr.'</div>';
        } else {
            foreach ($info as $ipInfo) {
                $ips[] = $ipInfo['ip'];
            }
            $location = '<div>'.implode('<br/>', $ips).'</div>';
        }

        if ($isStrongPrivacy) {
            $result = '<div><i>'.__('legacy/viewpeerlist.text_anonymous').'</i></div>';
            if ($canView) {
                $result = $location.$result;
            }
        } else {
            $result = $location;
        }

        return "<td class=rowfollow align=left width=1%><div class='nx-flex'>".$result.'</div></td>';
    }

    /**
     * @param  array<array<string, mixed>>  $arr
     * @param  array<string, mixed>  $torrent
     * @param  array<int, string>  $privacyData
     * @param  array<int, list<array<string, string>>>  $peerIpInfo
     * @param  array<int, string>  $usernameHtmlMap
     * @param  array<string, mixed>  $curUser
     */
    private function peerTable(string $name, array $arr, array $torrent, array $privacyData, bool $showLocationColumn, mixed $enablelocationTweak, array $peerIpInfo, array $usernameHtmlMap, array $curUser): string
    {
        $s = '<b>'.count($arr).' '.$name."</b>\n";
        if (! count($arr)) {
            return $s;
        }

        $s .= "\n";
        $s .= "<table width=100% class=main border=1 cellspacing=0 cellpadding=3>\n";
        $s .= '<tr><td class=colhead align=center width=1%>'.__('legacy/viewpeerlist.col_user_ip').'</td>'.
            ($showLocationColumn ? '<td class=colhead align=center>'.__('legacy/viewpeerlist.col_location').'</td>' : '').
            '<td class=colhead align=center width=1%>'.__('legacy/viewpeerlist.col_connectable').'</td>'.
            '<td class=colhead align=center width=1%>'.__('legacy/viewpeerlist.col_uploaded').'</td>'.
            '<td class=colhead align=center width=1%>'.__('legacy/viewpeerlist.col_rate').'</td>'.
            '<td class=colhead align=center width=1%>'.__('legacy/viewpeerlist.col_downloaded').'</td>'.
            '<td class=colhead align=center width=1%>'.__('legacy/viewpeerlist.col_rate').'</td>'.
            '<td class=colhead align=center width=1%>'.__('legacy/viewpeerlist.col_ratio').'</td>'.
            '<td class=colhead align=center width=1%>'.__('legacy/viewpeerlist.col_complete').'</td>'.
            '<td class=colhead align=center width=1%>'.__('legacy/viewpeerlist.col_connected').'</td>'.
            '<td class=colhead align=center width=1%>'.__('legacy/viewpeerlist.col_idle').'</td>'.
            '<td class=colhead align=center width=1%>'.__('legacy/viewpeerlist.col_client').'</td></tr>\n';
        $now = time();

        foreach ($arr as $e) {
            $privacy = $privacyData[$e['userid']] ?? '';
            $highlight = ($curUser['id'] ?? null) == $e['userid'] ? ' bgcolor=#BBAF9B' : '';
            $s .= "<tr$highlight>\n";
            $secs = max(1, ($e['la'] - $e['st']));
            $columnLocation = '';
            $currentUserId = (int) ($curUser['id'] ?? 0);
            $isStrongPrivacy = $privacy == 'strong' || (LegacyYesNo::isYes($torrent['anonymous'] ?? null) && $e['userid'] == $torrent['owner']);
            $canView = Permissions::userCan('viewanonymous', false, $currentUserId) || $e['userid'] == $currentUserId;
            if ($showLocationColumn) {
                $columnLocation = $this->peerLocationColumn($e, $isStrongPrivacy, $canView, $enablelocationTweak, $peerIpInfo);
            }

            $usernameHtml = $usernameHtmlMap[$e['userid']] ?? '';
            if ($isStrongPrivacy) {
                $columnUsername = '<td class=rowfollow align=left width=1%><i>'.__('legacy/viewpeerlist.text_anonymous').'</i>';
                if ($canView) {
                    $columnUsername .= '<br />('.$usernameHtml.')';
                }
                $columnUsername .= '</td>';
            } else {
                $columnUsername = '<td class=rowfollow align=left width=1%>'.$usernameHtml.'</td>';
            }

            $s .= $columnUsername.$columnLocation;

            $s .= '<td class=rowfollow align=center width=1%><nobr>'.(LegacyYesNo::isYes($e['connectable'] ?? null) ? __('legacy/viewpeerlist.text_yes') : '<font color=red>'.__('legacy/viewpeerlist.text_no').'</font>')."</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>'.Format::size((float) $e['uploaded'])."</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>'.Format::size(($e['uploaded'] - $e['uploadoffset']) / $secs)."/s</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>'.Format::size((float) $e['downloaded'])."</nobr></td>\n";

            if (LegacyYesNo::isNo($e['seeder'] ?? null)) {
                $s .= '<td class=rowfollow align=center width=1%><nobr>'.Format::size(($e['downloaded'] - $e['downloadoffset']) / $secs)."/s</nobr></td>\n";
            } else {
                $s .= '<td class=rowfollow align=center width=1%><nobr>'.Format::size(($e['downloaded'] - $e['downloadoffset']) / max(1, $e['finishedat'] - $e['st']))."/s</nobr></td>\n";
            }

            if ($e['downloaded']) {
                $ratio = floor(($e['uploaded'] / $e['downloaded']) * 1000) / 1000;
                $s .= '<td class=rowfollow align="center" width=1%><font color='.Ratio::color($ratio).'><nobr>'.number_format($ratio, 3)."</nobr></font></td>\n";
            } elseif ($e['uploaded']) {
                $s .= '<td class=rowfollow align=center width=1%>'.__('legacy/viewpeerlist.text_inf').'</td>';
            } else {
                $s .= '<td class=rowfollow align=center width=1%>---</td>';
            }

            $s .= '<td class=rowfollow align=center width=1%><nobr>'.sprintf('%.2f%%', 100 * (1 - ($e['to_go'] / max(1, $torrent['size']))))."</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>'.Format::prettyTimeWithLocale($now - $e['st'])."</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>'.Format::prettyTimeWithLocale($now - $e['la'])."</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>'.e(Strings::userAgentClient($e['agent']))."</nobr></td>\n";
            $s .= "</tr>\n";
        }

        $s .= "</table>\n";

        return $s;
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
     * @return list<array<string, string|SafeHtml>>
     */
    private function decorateSnatchRows(iterable $snatchedRows, int $currentUserId): array
    {
        $rows = [];
        foreach ($snatchedRows as $snatchRow) {
            $arr = (array) $snatchRow;
            if ($arr['downloaded'] > 0) {
                $ratio = number_format($arr['uploaded'] / $arr['downloaded'], 3);
                $ratio = '<font color='.Ratio::color($ratio).">$ratio</font>";
            } elseif ($arr['uploaded'] > 0) {
                $ratio = (string) (__('legacy/viewsnatches.text_inf'));
            } else {
                $ratio = '---';
            }
            $uploaded = Format::size((float) $arr['uploaded']);
            $downloaded = Format::size((float) $arr['downloaded']);
            $uprate = $arr['seedtime'] > 0
                ? Format::size($arr['uploaded'] / ($arr['seedtime'] + $arr['leechtime']))
                : Format::size(0);
            $downrate = $arr['leechtime'] > 0
                ? Format::size($arr['downloaded'] / $arr['leechtime'])
                : Format::size(0);

            $userrow = UserDisplay::row($arr['userid']);
            $privacy = is_array($userrow) ? (string) ($userrow['privacy'] ?? '') : '';
            if ($privacy == 'strong') {
                $username = (string) (__('legacy/viewsnatches.text_anonymous'));
                if (Permission::can(PermissionEnum::VIEW_ANONYMOUS) || $arr['id'] == $currentUserId) {
                    $username .= '<br />('.UserDisplay::username($arr['userid']).')';
                }
            } else {
                $username = UserDisplay::username($arr['userid']);
            }
            $reportImage = '<img class="f_report" src="pic/trans.gif" alt="Report" title="'.e((string) (__('legacy/viewsnatches.title_report'))).'" />';
            $reportHtml = $privacy != 'strong' || Permission::can(PermissionEnum::VIEW_ANONYMOUS)
                ? '<a href=report.php?user='.(int) $arr['userid'].'>'.$reportImage.'</a>'
                : $reportImage;

            $rows[] = [
                'highlight' => SafeHtml::fromTrustedHtml($currentUserId == $arr['userid'] ? ' bgcolor=#00A527' : ''),
                'usernameHtml' => SafeHtml::fromTrustedHtml($username),
                'ip' => (string) ($arr['ip'] ?? ''),
                'trafficHtml' => SafeHtml::fromTrustedHtml($uploaded.'@'.$uprate.(__('legacy/viewsnatches.text_per_second')).'<br />'.$downloaded.'@'.$downrate.(__('legacy/viewsnatches.text_per_second'))),
                'ratioHtml' => SafeHtml::fromTrustedHtml($ratio),
                'seedtime' => Format::prettyTimeWithLocale((float) $arr['seedtime']),
                'leechtime' => Format::prettyTimeWithLocale((float) $arr['leechtime']),
                'completedAtHtml' => SafeHtml::fromTrustedHtml((string) Time::format($arr['completedat'], true, false)),
                'lastActionHtml' => SafeHtml::fromTrustedHtml((string) Time::format($arr['last_action'], true, false)),
                'reportHtml' => SafeHtml::fromTrustedHtml($reportHtml),
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
        $currentUser = ! empty($curUser) ? User::query()->find((int) ($curUser['id'] ?? 0)) : null;

        if ($currentUser === null || (! Permissions::userCan(PermissionEnum::TORRENT_HISTORY->value, false, $currentUser->id) && $currentUser->id !== $targetUserId)) {
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
        $curUserArr = $curUser;

        $torrentlist = '';
        if ($data['count'] > 0 && ! empty($data['rows'])) {
            [$torrentlist] = $this->torrentListTable($data['rows'], $type, $targetUserId, $curUserArr, $data['seedTimeAndUploaded'], $data['torrentRep']);
        }

        $table = $data['pagertop'].$torrentlist.$data['pagerbottom'];
        $hasData = false;
        $summary = sprintf('<b>%s</b>%s', $data['count'], (__('legacy/getusertorrentlistajax.text_record')).Strings::addS($data['count']));
        if ($data['total_size']) {
            $hasData = true;
            $summary .= (__('legacy/getusertorrentlistajax.text_total_size')).Format::size((float) $data['total_size']);
        } elseif ($data['count']) {
            $hasData = true;
        }

        $data['bodyHtml'] = SafeHtml::fromTrustedHtml($hasData
            ? '<br/>'.sprintf('<div class="nx-flex-between"><div>%s</div><div></div></div>', $summary).$table
            : (string) (__('legacy/getusertorrentlistajax.text_no_record')));

        return response()->view('getusertorrentlistajax.index', $data, 200, $headers);
    }

    /**
     * @param  iterable<int, mixed>  $rows
     * @param  array<string, mixed>  $currentUser
     * @return array{string, float}
     */
    private function torrentListTable(iterable $rows, string $mode, int $id, array $currentUser, mixed $seedTimeAndUploaded, mixed $torrentRep): array
    {
        $showsize = $showsenum = $showlenum = $showuploaded = $showdownloaded = $showratio = $showsetime = $showletime = $showcotime = $showanonymous = $showtotalsize = false;
        $columncount = 7;
        $showClient = false;
        switch ($mode) {
            case 'uploaded':
                $showsize = true;
                $showsenum = true;
                $showlenum = true;
                $showuploaded = true;
                $showdownloaded = false;
                $showratio = false;
                $showsetime = true;
                $showletime = false;
                $showcotime = false;
                $showanonymous = true;
                $showtotalsize = true;
                $columncount = 8;
                break;
            case 'seeding':
                $showsize = true;
                $showsenum = true;
                $showlenum = true;
                $showuploaded = true;
                $showdownloaded = true;
                $showratio = true;
                $showsetime = true;
                $showletime = false;
                $showcotime = false;
                $showanonymous = false;
                $showtotalsize = true;
                $columncount = 8;
                $showClient = true;
                break;
            case 'leeching':
                $showsize = true;
                $showsenum = true;
                $showlenum = true;
                $showuploaded = true;
                $showdownloaded = true;
                $showratio = true;
                $showsetime = false;
                $showletime = false;
                $showcotime = false;
                $showanonymous = false;
                $showtotalsize = true;
                $columncount = 8;
                $showClient = true;
                break;
            case 'completed':
                $showsize = true;
                $showsenum = false;
                $showlenum = false;
                $showuploaded = true;
                $showdownloaded = false;
                $showratio = false;
                $showsetime = true;
                $showletime = true;
                $showcotime = true;
                $showanonymous = false;
                $showtotalsize = false;
                break;
            case 'incomplete':
                $showsize = true;
                $showsenum = false;
                $showlenum = false;
                $showuploaded = true;
                $showdownloaded = true;
                $showratio = true;
                $showsetime = false;
                $showletime = true;
                $showcotime = false;
                $showanonymous = false;
                $showtotalsize = false;
                $columncount = 7;
                break;
        }

        $shouldShowClient = false;
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        if ($showClient && (Permissions::userCan(PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO->value, false, $currentUserId) || $currentUserId == $id)) {
            $shouldShowClient = true;
        }

        $results = [];
        foreach ($rows as $row) {
            $results[] = (array) $row;
        }

        $ret = '<table border="1" cellspacing="0" cellpadding="5" width="100%"><tr><td class="colhead">'.__('legacy/getusertorrentlistajax.col_type').'</td><td class="colhead" align="center">'.__('legacy/getusertorrentlistajax.col_name').'</td><td class="colhead" align="center">'.__('legacy/getusertorrentlistajax.col_added').'</td>'.
            ($showsize ? '<td class="colhead" align="center"><img class="size" src="pic/trans.gif" alt="size" title="'.__('legacy/getusertorrentlistajax.title_size').'" /></td>' : '').
            ($showsenum ? '<td class="colhead" align="center"><img class="seeders" src="pic/trans.gif" alt="seeders" title="'.__('legacy/getusertorrentlistajax.title_seeders').'" /></td>' : '').
            ($showlenum ? '<td class="colhead" align="center"><img class="leechers" src="pic/trans.gif" alt="leechers" title="'.__('legacy/getusertorrentlistajax.title_leechers').'" /></td>' : '').
            ($showuploaded ? '<td class="colhead" align="center">'.__('legacy/getusertorrentlistajax.col_uploaded').'</td>' : '').
            ($showdownloaded ? '<td class="colhead" align="center">'.__('legacy/getusertorrentlistajax.col_downloaded').'</td>' : '').
            ($showratio ? '<td class="colhead" align="center">'.__('legacy/getusertorrentlistajax.col_ratio').'</td>' : '').
            ($showsetime ? '<td class="colhead" align="center">'.__('legacy/getusertorrentlistajax.col_se_time').'</td>' : '').
            ($showletime ? '<td class="colhead" align="center">'.__('legacy/getusertorrentlistajax.col_le_time').'</td>' : '').
            ($showcotime ? '<td class="colhead" align="center">'.__('legacy/getusertorrentlistajax.col_time_completed').'</td>' : '').
            ($showanonymous ? '<td class="colhead" align="center">'.__('legacy/getusertorrentlistajax.col_anonymous').'</td>' : '');
        if ($shouldShowClient) {
            $ret .= sprintf('<td class="colhead" align="center">%s</td><td class="colhead" align="center">IP</td>', __('legacy/getusertorrentlistajax.col_client'));
        }
        $ret .= '</tr>';

        $totalSize = 0;
        foreach ($results as $arr) {
            if ($mode == 'uploaded') {
                $seedTimeAndUploadedData = $seedTimeAndUploaded->get($arr['torrent']);
                $arr['seedtime'] = $seedTimeAndUploadedData ? $seedTimeAndUploadedData->seedtime : 0;
                $arr['uploaded'] = $seedTimeAndUploadedData ? $seedTimeAndUploadedData->uploaded : 0;
            }

            $sphighlight = Promotion::backgroundStyleWithContext($arr['sp_state']);
            $bannedTorrent = (LegacyYesNo::isYes($arr['banned'] ?? null) ? ' <b>(<font class="striking">'.__('legacy/functions.text_banned').'</font>)</b>' : '');
            $spTorrent = Promotion::appendWithContext($arr['sp_state'], '', false, '', 0, '', $arr['__ignore_global_sp_state'] ?? false);
            if ($showtotalsize) {
                $totalSize += $arr['size'];
            }

            $hrImg = TorrentAccess::hrImage($arr, $arr['search_box_id']);
            $approvalStatusIcon = $torrentRep->renderApprovalStatus($arr['approval_status']);

            $dispname = $nametitle = e($arr['torrentname']);
            $countDispname = mb_strlen($dispname, 'UTF-8');
            $maxLenghtOfTorrentName = ($currentUser['fontsize'] == 'large' ? 70 : 80);
            if ($countDispname > $maxLenghtOfTorrentName) {
                $dispname = mb_substr($dispname, 0, $maxLenghtOfTorrentName, 'UTF-8').'..';
            }

            $ret .= '<tr'.$sphighlight.'><td class="rowfollow nowrap" valign="middle">'.
                Category::imageTagWithContext($arr['category'], 'torrents.php?allsec=1&amp;').
                "</td>\n".
                '<td class="rowfollow" width="100%" align="left"><a href="'.
                e('details.php?id='.$arr['torrent'].'&hit=1').
                '" title="'.$nametitle.'"><b>'.$dispname.'</b></a>'.
                $bannedTorrent.$spTorrent.$hrImg.$approvalStatusIcon.'</td>';
            $ret .= sprintf('<td class="rowfollow nowrap" align="center">%s<br/>%s</td>', substr($arr['added'], 0, 10), substr($arr['added'], 11));

            if ($showsize) {
                $ret .= '<td class="rowfollow" align="center">'.Format::sizeCompact($arr['size']).'</td>';
            }
            if ($showsenum) {
                $ret .= '<td class="rowfollow" align="center">'.$arr['seeders'].'</td>';
            }
            if ($showlenum) {
                $ret .= '<td class="rowfollow" align="center">'.$arr['leechers'].'</td>';
            }
            if ($showuploaded) {
                $ret .= '<td class="rowfollow" align="center">'.Format::sizeCompact($arr['uploaded']).'</td>';
            }
            if ($showdownloaded) {
                $ret .= '<td class="rowfollow" align="center">'.Format::sizeCompact($arr['downloaded']).'</td>';
            }
            if ($showratio) {
                if ($arr['downloaded'] > 0) {
                    $ratio = number_format($arr['uploaded'] / $arr['downloaded'], 3);
                    $ratio = '<font color="'.Ratio::color($ratio).'">'.$ratio.'</font>';
                } elseif ($arr['uploaded'] > 0) {
                    $ratio = 'Inf.';
                } else {
                    $ratio = '---';
                }
                $ret .= '<td class="rowfollow" align="center">'.$ratio.'</td>';
            }
            if ($showsetime) {
                $ret .= '<td class="rowfollow" align="center">'.Format::prettyTimeWithLocale($arr['seedtime']).'</td>';
            }
            if ($showletime) {
                $ret .= '<td class="rowfollow" align="center">'.Format::prettyTimeWithLocale($arr['leechtime']).'</td>';
            }
            if ($showcotime) {
                $ret .= '<td class="rowfollow" align="center">'.''.str_replace('&nbsp;', '<br />', (string) Time::format($arr['completedat'], false)).'</td>';
            }
            if ($showanonymous) {
                $ret .= '<td class="rowfollow" align="center">'.$arr['anonymous'].'</td>';
            }
            if ($shouldShowClient) {
                $ipArr = array_filter([$arr['ipv4'], $arr['ipv6']]);
                foreach ($ipArr as &$_ip) {
                    $_ip = sprintf('<span class="nowrap">%s</span>', $_ip);
                }
                $ret .= sprintf(
                    '<td class="rowfollow" align="center">%s<br/>%s</td><td class="rowfollow" align="center">%s</td>',
                    Strings::userAgentClient($arr['agent']), $arr['port'],
                    implode('<br/>', $ipArr)
                );
            }
            $ret .= "</tr>\n";
        }

        $ret .= "</table>\n";

        return [$ret, $totalSize];
    }

    /**
     * Maps a file extension to a category used for badge color.
     */
    private function fileExtCategory(string $ext): string
    {
        static $map = [
            'video' => ['mkv', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'ts', 'm2ts', 'mts', 'webm', 'mpg', 'mpeg', 'vob', 'rm', 'rmvb', 'm4v', '3gp', 'ogv', 'asf', 'divx', 'mxf'],
            'audio' => ['mp3', 'flac', 'wav', 'ogg', 'm4a', 'aac', 'opus', 'wma', 'ape', 'alac', 'dts', 'ac3', 'mka', 'mp2', 'mid', 'midi', 'tak', 'tta', 'wv'],
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'svg', 'heic', 'heif', 'ico', 'psd', 'raw', 'arw', 'cr2', 'nef'],
            'subtitle' => ['srt', 'ass', 'ssa', 'sub', 'idx', 'vtt', 'sup', 'smi', 'sbv'],
            'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'zst', 'lz', 'lzma', 'tbz2', 'tgz', 'txz', 'cab', 'arj'],
            'iso' => ['iso', 'img', 'mds', 'mdf', 'bin', 'cue', 'nrg', 'dmg', 'vhd', 'vmdk'],
            'document' => ['pdf', 'epub', 'mobi', 'azw', 'azw3', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf', 'djvu', 'fb2', 'chm', 'odt', 'ods', 'odp'],
            'text' => ['txt', 'md', 'log', 'sfv', 'md5', 'sha1', 'sha256', 'par', 'par2', 'json', 'xml', 'yaml', 'yml', 'csv', 'ini'],
            'nfo' => ['nfo'],
            'code' => ['php', 'js', 'ts', 'py', 'rb', 'go', 'rs', 'c', 'h', 'cpp', 'hpp', 'cs', 'java', 'sh', 'sql', 'html', 'css', 'scss', 'vue'],
            'exec' => ['exe', 'msi', 'app', 'deb', 'rpm', 'apk', 'dmg', 'pkg', 'run', 'bat', 'cmd', 'ps1', 'jar'],
            'torrent' => ['torrent'],
        ];
        foreach ($map as $cat => $list) {
            if (in_array($ext, $list, true)) {
                return $cat;
            }
        }

        return 'other';
    }

    /**
     * Renders a small colored extension badge for a filename.
     */
    private function fileBadge(string $filename): string
    {
        $dot = strrpos($filename, '.');
        $ext = $dot !== false ? strtolower(substr($filename, $dot + 1)) : '';
        if ($ext === '' || strlen($ext) > 5 || ! ctype_alnum($ext)) {
            $cat = 'other';
            $label = '?';
        } else {
            $cat = $this->fileExtCategory($ext);
            $label = strtoupper($ext);
        }

        return '<span class="fileicon fi-'.e($cat).'" title="'.e($cat).'">'.e($label).'</span>';
    }

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
        $user = User::query()->find($userId);

        if ($user === null) {
            return response()->json(['torrents' => []]);
        }

        return response()->json($this->torrentAjaxRepository->autocompleteTorrents($query, $user));
    }
}
