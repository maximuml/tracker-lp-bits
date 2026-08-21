<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Models\Peer;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\SearchRepository;
use App\Repositories\TorrentAjaxRepository;
use App\Repositories\TorrentRepository;
use App\Support\Bonus;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Hooks;
use App\Support\Http;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Path;
use App\Support\Permissions;
use App\Support\Strings;
use App\Support\SupportContext;
use App\Support\Time;
use App\Support\TorrentBookmark;
use App\Support\TorrentOps;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Database\NexusDB;
use Rhilip\Bencode\Bencode;

class TorrentActionController extends LegacyController
{
    public function bookmark(Request $request): Response
    {
        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/xml; charset=utf-8',
        ];

        $user = SupportContext::getUser();
        if ($user === null) {
            return response('failed', 200, $headers);
        }

        $torrentId = (int) $request->input('torrentid', 0);
        if ($torrentId <= 0) {
            return response('failed', 200, $headers);
        }

        $userId = (int) $user['id'];
        $bookmark = NexusDB::table('bookmarks')->where('torrentid', $torrentId)->where('userid', $userId)->first();

        $searchRep = new SearchRepository;
        if ($bookmark) {
            $bookmarkId = (int) $bookmark->id;
            $searchRep->deleteBookmark($bookmarkId);
            NexusDB::table('bookmarks')->where('id', $bookmarkId)->delete();
            $status = 'deleted';
        } else {
            $bookmarkId = NexusDB::table('bookmarks')->insertGetId([
                'torrentid' => $torrentId,
                'userid' => $userId,
            ]);
            $searchRep->addBookmark($bookmarkId);
            $status = 'added';
        }

        $cache = SupportContext::getCache();
        if ($cache !== null) {
            $cache->delete_value('user_'.$userId.'_bookmark_array');
        }

        return response($status, 200, $headers);
    }

    public function fastDelete(Request $request): Response|RedirectResponse
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/fastdelete.php'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);

        $id = (int) SupportContext::getRequestInput('id');
        if ($id <= 0) {
            $lang = (array) SupportContext::getGlobal('lang_fastdelete', []);

            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['std_missing_form_data'] ?? 'Invalid id.');
        }

        if (! Permissions::userCan(PermissionEnum::TORRENT_MANAGE->value, false, $currentUserId)
            || ! Permissions::userCan(PermissionEnum::TORRENT_DELETE->value, false, $currentUserId)) {
            $lang = (array) SupportContext::getGlobal('lang_fastdelete', []);

            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['text_no_permission'] ?? 'No permission.');
        }

        $torrent = Torrent::query()->where('id', $id)->first(['name', 'owner', 'seeders', 'anonymous']);
        if (! $torrent instanceof Torrent) {
            return redirect('/torrents.php');
        }
        $row = $torrent->toArray();

        $sure = SupportContext::getQuery('sure');
        if (empty($sure)) {
            $lang = (array) SupportContext::getGlobal('lang_fastdelete', []);

            return $this->legacyAbortResponse(
                $lang['std_delete_torrent'] ?? 'Delete torrent',
                ($lang['std_delete_torrent_note'] ?? '')."<a class=altlink href=fastdelete.php?id=$id&sure=1>".($lang['std_here_if_sure'] ?? 'here').'</a>',
                false
            );
        }

        $searchRep = new SearchRepository;
        if ($searchRep->deleteTorrent($id) === false) {
            $lang = (array) SupportContext::getGlobal('lang_fastdelete', []);

            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', 'Delete es fail.');
        }

        TorrentOps::deleteTorrents($id, false);

        $uploadtorrentBonus = (float) SupportContext::getGlobal('uploadtorrent_bonus', 0);
        Bonus::updatePoints('-', $uploadtorrentBonus, (int) $row['owner']);

        if ($row['anonymous'] === 'yes' && $currentUserId == $row['owner']) {
            Log::writeWithContext("Torrent $id ({$row['name']}) was deleted by its anonymous uploader", 'normal');
        } else {
            Log::writeWithContext("Torrent $id ({$row['name']}) was deleted by {$curUser['username']}", 'normal');
        }

        if ($currentUserId != $row['owner'] && User::query()->where('id', $row['owner'])->exists()) {
            $locale = Locale::userLocale((int) $row['owner']);
            $dt = date('Y-m-d H:i:s');
            $subject = Locale::trans('torrent.msg_torrent_deleted', [], $locale);
            $msg = Locale::trans('torrent.msg_the_torrent_you_uploaded', [], $locale)
                .$row['name']
                .Locale::trans('torrent.msg_was_deleted_by', ['admin' => $curUser['username']], $locale);
            Message::add([
                'sender' => 0,
                'receiver' => $row['owner'],
                'subject' => $subject,
                'msg' => $msg,
                'added' => $dt,
            ]);
        }

        return redirect('/torrents.php');
    }

    public function torrentInfo(Request $request): View|RedirectResponse|Response
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            abort(404);
        }

        $torrent = Torrent::query()->find($id, ['id', 'name']);
        if (! $torrent instanceof Torrent) {
            abort(404);
        }

        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);
        if (! Permissions::userCan(PermissionEnum::TORRENT_STRUCTURE->value, false, $currentUserId)) {
            abort(403);
        }

        $torrentDir = SiteConfig::current()->main->torrentDir();
        $filePath = Path::resolve("{$torrentDir}/{$id}.torrent", \ROOT_PATH);
        if (! is_file($filePath) || ! is_readable($filePath)) {
            abort(404);
        }

        $dict = Bencode::load($filePath);

        return $this->legacyPage($request, 'torrent_info', true, [
            'torrentName' => (string) $torrent->name,
            'dict' => $dict,
        ]);
    }

    public function viewFileList(Request $request): Response|RedirectResponse
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return response('', 400, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $files = TorrentAjaxRepository::fileList($torrentId);

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

        $curUser = SupportContext::getUser() ?? [];
        $currentUser = ! empty($curUser) ? User::query()->find((int) ($curUser['id'] ?? 0)) : null;

        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/html; charset=utf-8',
        ];

        return response()->view('viewpeerlist.index', TorrentAjaxRepository::peerList($torrentId, $currentUser), 200, $headers);
    }

    public function viewSnatches(Request $request): View|RedirectResponse|Response
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return redirect('/torrents.php');
        }

        return $this->legacyPage($request, 'viewsnatches', true, TorrentAjaxRepository::snatchList($torrentId));
    }

    public function takeFlush(Request $request): Response|RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return $this->legacyAbortResponse('Error', 'Invalid ID.');
        }

        $currentUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $currentClass = (int) UserDisplay::currentClass();

        $lang = (array) (SupportContext::getGlobal('lang_takeflush') ?? []);

        if ($currentClass >= User::CLASS_MODERATOR || $currentUserId === $id) {
            $deadtime = Time::deadThreshold(SiteConfig::current()->main->anninterthree());
            $lastAction = date('Y-m-d H:i:s', $deadtime);
            $effected = Peer::query()->where('last_action', '<', $lastAction)->where('userid', $id)->delete();

            return $this->legacyAbortResponse(
                $lang['std_success'] ?? 'Success',
                $effected.' '.($lang['std_ghost_torrents_cleaned'] ?? 'ghost torrent(s) cleaned.')
            );
        }

        return $this->legacyAbortResponse(
            $lang['std_failed'] ?? 'Failed',
            $lang['std_cannot_flush_others'] ?? 'You cannot flush other users.'
        );
    }

    public function takeReseed(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/takereseed.php'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);
        if (! Permissions::userCan(PermissionEnum::ASK_RESEED->value, false, $currentUserId)) {
            $lang = (array) SupportContext::getGlobal('lang_takereseed', []);

            return $this->legacyAbortResponse($lang['std_error'] ?? 'Error', $lang['std_permission_denied'] ?? 'Permission denied.');
        }

        $reseedid = (int) (SupportContext::getQuery('reseedid') ?? SupportContext::getQuery('id') ?? 0);
        $torrent = Torrent::query()->find($reseedid);
        $row = $torrent instanceof Torrent ? $torrent->toArray() : null;

        $seederCount = (int) Peer::query()->where('torrent', $reseedid)->count();
        $lang = (array) SupportContext::getGlobal('lang_takereseed', []);

        if ($seederCount > 0) {
            return $this->legacyAbortResponse($lang['std_error'] ?? 'Error', $lang['std_torrent_not_dead'] ?? 'Torrent is not dead.');
        }

        $timeNow = (int) SupportContext::getGlobal('TIMENOW', time());
        if ($row !== null && strtotime((string) ($row['last_reseed'] ?? '')) > ($timeNow - 900)) {
            return $this->legacyAbortResponse($lang['std_error'] ?? 'Error', $lang['std_reseed_sent_recently'] ?? 'Reseed request sent recently.');
        }

        $snatchedRows = NexusDB::table('snatched')
            ->join('users', 'snatched.userid', '=', 'users.id')
            ->join('torrents', 'snatched.torrentid', '=', 'torrents.id')
            ->where('snatched.finished', 'Yes')
            ->where('snatched.torrentid', $reseedid)
            ->select('snatched.userid', 'snatched.torrentid', 'torrents.name as torrent_name', 'users.id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');
        foreach ($snatchedRows as $snatchRow) {
            $locale = Locale::userLocale((int) $snatchRow['userid']);
            $rsSubject = Locale::trans('torrent.msg_reseed_request', [], $locale);
            $pnMsg = Locale::trans('torrent.msg_reseed_user', [], $locale)
                .$curUser['username']
                .Locale::trans('torrent.msg_ask_reseed', [], $locale)
                .'[url='.Http::protocolPrefix(Url::isSecure()).$baseUrl.'/details.php?id='.$reseedid.']'.$snatchRow['torrent_name'].'[/url]'
                .Locale::trans('torrent.msg_thank_you', [], $locale);
            Message::add([
                'sender' => 0,
                'receiver' => $snatchRow['userid'],
                'subject' => $rsSubject,
                'msg' => $pnMsg,
                'added' => now(),
            ]);
        }

        Torrent::query()->where('id', $reseedid)->update([
            'last_reseed' => now(),
            'seeders' => $seederCount,
        ]);

        return $this->legacyPage($request, 'takereseed', true, [
            'message' => $lang['std_it_worked'] ?? 'Reseed request sent.',
        ]);
    }

    public function getUserTorrentListAjax(Request $request): Response|RedirectResponse
    {
        $targetUserId = (int) $request->input('userid', 0);
        $type = (string) $request->input('type', '');

        if ($targetUserId <= 0 || ! in_array($type, ['uploaded', 'seeding', 'leeching', 'completed', 'incomplete'], true)) {
            return response('', 400, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $curUser = SupportContext::getUser() ?? [];
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

        return response()->view('getusertorrentlistajax.index', TorrentAjaxRepository::userTorrentList($targetUserId, $type, $page, $currentUser), 200, $headers);
    }

    public function searchSuggest(Request $request): Response|RedirectResponse
    {
        $searchstr = (string) $request->input('q', '');
        if ($searchstr === '') {
            return response((string) json_encode([], JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        return response(
            (string) json_encode(TorrentAjaxRepository::searchSuggest($searchstr), JSON_UNESCAPED_UNICODE),
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

        $userId = (int) (SupportContext::getUser()['id'] ?? 0);
        $user = User::query()->find($userId);

        if ($user === null) {
            return response()->json(['torrents' => []]);
        }

        return response()->json(TorrentAjaxRepository::autocompleteTorrents($query, $user));
    }

    public function torrentrss(Request $request): Response
    {
        $cache = SupportContext::getCache();
        $currentUser = SupportContext::getUser() ?? [];
        $passkey = (string) ($request->input('passkey') ?? $currentUser['passkey'] ?? '');

        if ($passkey === '') {
            return response('require passkey', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $exactParams = ['inclbookmarked', 'paid', 'rows', 'icat', 'ismalldescr', 'isize', 'iuplder', 'search', 'search_mode', 'sticky', 'linktype'];
        $filteredQuery = [];
        foreach ($request->query->all() as $key => $value) {
            if (in_array($key, $exactParams, true)) {
                $filteredQuery[$key] = $value;

                continue;
            }
            if (preg_match('/^(cat|sou|med|cod|sta|pro|tea|aud)\d+$/', $key)) {
                $filteredQuery[$key] = $value;
            }
        }

        $cacheKey = 'nexus_rss:'.$passkey.':'.md5(http_build_query($filteredQuery));
        $cacheData = NexusDB::cache_get($cacheKey);
        if ($cacheData && config('app.env') !== 'local') {
            Log::writeWithContext('rss get from cache', 'info');

            return response((string) $cacheData, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
        }

        $showrows = (int) $request->input('rows', 0);
        if ($showrows < 1 || $showrows > 50) {
            $showrows = 50;
        }

        $paidFilter = '0';
        if ($request->input('paid') !== null && in_array((string) $request->input('paid'), ['0', '1', '2'], true)) {
            $paidFilter = (string) $request->input('paid');
        }

        $baseQuery = NexusDB::table('torrents')
            ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
            ->leftJoin('torrent_extras', 'torrents.id', '=', 'torrent_extras.torrent_id')
            ->select('torrents.id', 'torrents.category', 'torrents.name', 'torrent_extras.descr', 'torrents.info_hash', 'torrents.size', 'torrents.added', 'torrents.anonymous', 'torrents.owner', 'categories.name as category_name');

        $dllink = false;
        $inclbookmarked = 0;
        $rssUser = (array) NexusDB::remember('user_passkey_'.$passkey.'_rss', 3600, function () use ($passkey) {
            $row = NexusDB::table('users')->where('passkey', $passkey)->first(['id', 'enabled', 'parked', 'passkey']);

            return $row ? (array) $row : [];
        });

        if (empty($rssUser)) {
            return response('invalid passkey', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($rssUser['enabled'] === 'no' || $rssUser['parked'] === 'yes') {
            return response('account disabed or parked', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($request->input('linktype') === 'dl') {
            $dllink = true;
        }

        $inclbookmarked = (int) $request->input('inclbookmarked', 0);
        if ($inclbookmarked === 1) {
            $bookmarkarray = TorrentBookmark::bookmarkArray($cache, (int) ($rssUser['id'] ?? 0));
            if (! empty($bookmarkarray)) {
                $baseQuery->whereIn('torrents.id', $bookmarkarray);
            }
        }

        if (! SiteConfig::current()->torrent->approvalStatusNoneVisible() && ! Permissions::userCan(PermissionEnum::STAFF_MEMBER->value, false, (int) ($rssUser['id'] ?? 0))) {
            $baseQuery->where('torrents.approval_status', Torrent::APPROVAL_STATUS_ALLOW);
        }

        $browseMode = SiteConfig::current()->main->browseCat();
        $allBrowseCategoryId = SearchBox::listCategoryId($browseMode);
        $baseQuery->whereIn('torrents.category', $allBrowseCategoryId);

        $baseQuery->where('torrents.visible', 'yes');

        if ($paidFilter === '0') {
            $baseQuery->where('torrents.price', 0);
        } elseif ($paidFilter === '1') {
            $baseQuery->where('torrents.price', '>', 0);
        }

        $applyRssFilter = function ($query, string $tablename = 'sources', string $itemname = 'source', string $getname = 'sou') use ($request) {
            $items = \App\Support\SearchBox::itemListWithContext($tablename, 0);
            $ids = [];
            foreach ($items as $item) {
                if ($request->input($getname.$item['id']) !== null) {
                    $ids[] = $item['id'];
                }
            }
            if (! empty($ids)) {
                $query->whereIn($itemname, $ids);
            }
        };

        $applyRssFilter($baseQuery, 'categories', 'category', 'cat');
        $applyRssFilter($baseQuery, 'sources', 'source', 'sou');
        $applyRssFilter($baseQuery, 'media', 'medium', 'med');
        $applyRssFilter($baseQuery, 'codecs', 'codec', 'cod');
        $applyRssFilter($baseQuery, 'standards', 'standard', 'sta');
        $applyRssFilter($baseQuery, 'processings', 'processing', 'pro');
        $applyRssFilter($baseQuery, 'audiocodecs', 'audiocodec', 'aud');

        $hasStickyFirst = false;
        $hasStickySecond = false;
        $hasStickyNormal = false;
        $noNormalResults = false;
        $prependIdArr = [];
        $prependRows = [];
        $normalRows = [];

        if ($request->input('sticky') !== null && $inclbookmarked === 0) {
            $stickyArr = explode(',', (string) $request->input('sticky'));
            $posStates = [];
            if (in_array('0', $stickyArr, true)) {
                $hasStickyNormal = true;
            }
            if (in_array('1', $stickyArr, true)) {
                $hasStickyFirst = true;
                $posStates[] = Torrent::POS_STATE_STICKY_FIRST;
            }
            if (in_array('2', $stickyArr, true)) {
                $hasStickySecond = true;
                $posStates[] = Torrent::POS_STATE_STICKY_SECOND;
            }
            if (! empty($posStates)) {
                $prependIdArr = Torrent::query()->whereIn('pos_state', $posStates)->pluck('id')->toArray();
            }
        }

        $prependIdArr = Hooks::applyFilter('sticky_promotion_torrent_ids', $prependIdArr);

        if ($hasStickyFirst || $hasStickySecond) {
            $noNormalResults = true;
        }

        if (! $noNormalResults) {
            $normalQuery = clone $baseQuery;
            if ($hasStickyNormal) {
                $normalQuery->where('torrents.pos_state', Torrent::POS_STATE_STICKY_NONE);
            }
            $normalSql = $normalQuery->toSql();
            $normalCacheKey = sprintf('nexus_rss:normal:%s', md5($normalSql.':'.$showrows));
            $normalRows = NexusDB::remember($normalCacheKey, 300, function () use ($normalQuery, $showrows) {
                return $normalQuery->orderBy('torrents.id', 'desc')->limit($showrows)->get()->map(fn ($row) => (array) $row)->all();
            });
        }

        if (! empty($prependIdArr)) {
            $prependIdStr = implode(',', array_map('intval', $prependIdArr));
            $prependQuery = clone $baseQuery;
            $prependQuery->whereIn('torrents.id', $prependIdArr);
            $prependCacheKey = sprintf('nexus_rss:prepend:%s', md5($prependQuery->toSql().':'.$prependIdStr));
            $prependRows = NexusDB::remember($prependCacheKey, 300, function () use ($prependQuery, $prependIdStr) {
                return $prependQuery->orderByRaw('FIELD(torrents.id, '.$prependIdStr.')')->get()->map(fn ($row) => (array) $row)->all();
            });
        }

        $list = [];
        foreach ($prependRows as $row) {
            $list[(int) $row['id']] = $row;
        }
        foreach ($normalRows as $row) {
            if (! isset($list[(int) $row['id']])) {
                $list[(int) $row['id']] = $row;
            }
        }

        $torrentRep = new TorrentRepository;
        $baseUrl = Http::protocolPrefix(Url::isSecure()).(string) SupportContext::getGlobal('BASEURL', '');
        $siteName = (string) SupportContext::getGlobal('SITENAME', '');
        $slogan = (string) SupportContext::getGlobal('SLOGAN', '');
        $siteEmail = (string) SupportContext::getGlobal('SITEEMAIL', '');
        $projectName = (string) SupportContext::getGlobal('PROJECTNAME', '');
        $dateFounded = (string) SupportContext::getGlobal('datefounded', '');
        $year = substr($dateFounded, 0, 4);
        $yearFounded = $year !== '' ? $year : '2007';
        $copyright = 'Copyright (c) '.$siteName.' '.(date('Y') !== $yearFounded ? $yearFounded.'-' : '').date('Y').', all rights reserved';
        $httpHost = (string) $request->server->get('HTTP_HOST', 'localhost');

        $hexEsc = function ($matches) {
            return sprintf('%02x', ord($matches[0]));
        };

        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<rss version="2.0">';
        $xml .= '<channel>
        <title>'.addslashes($siteName.' Torrents').'</title>
        <link><![CDATA['.$baseUrl.']]></link>
        <description><![CDATA['.addslashes('Latest torrents from '.$siteName.' - '.htmlspecialchars($slogan)).']]></description>
        <language>zh-cn</language>
        <copyright>'.$copyright.'</copyright>
        <managingEditor>'.$siteEmail.' ('.$siteName.' Admin)</managingEditor>
        <webMaster>'.$siteEmail.' ('.$siteName.' Webmaster)</webMaster>
        <pubDate>'.date('r').'</pubDate>
        <generator>'.$projectName.' RSS Generator</generator>
        <docs><![CDATA[http://www.rssboard.org/rss-specification]]></docs>
        <ttl>60</ttl>
        <image>
            <url><![CDATA['.$baseUrl.'/pic/rss_logo.jpg]]></url>
            <title>'.addslashes($siteName.' Torrents').'</title>
            <link><![CDATA['.$baseUrl.']]></link>
            <width>100</width>
            <height>100</height>
            <description>'.addslashes($siteName.' Torrents').'</description>
        </image>';

        foreach ($list as $row) {
            $ownerInfo = UserDisplay::row((int) ($row['owner'] ?? 0));
            $author = 'anonymous';
            if ($row['anonymous'] !== 'yes') {
                if (! empty($ownerInfo)) {
                    $author = (string) ($ownerInfo['username'] ?? '');
                } else {
                    $author = Locale::trans('nexus.user_not_exists', [], null);
                }
            }

            $itemurl = $baseUrl.'/details.php?id='.(int) ($row['id'] ?? 0);
            if ($dllink) {
                $itemdlurl = $torrentRep->getDownloadUrl((int) ($row['id'] ?? 0), $rssUser);
            } else {
                $itemdlurl = $baseUrl.'/download.php?id='.(int) ($row['id'] ?? 0);
            }

            $title = '';
            if ($request->input('icat') !== null) {
                $title .= '['.($row['category_name'] ?? '').']';
            }
            $title .= $row['name'] ?? '';
            if ($request->input('isize') !== null) {
                $title .= '['.Format::size((int) ($row['size'] ?? 0)).']';
            }
            if ($request->input('iuplder') !== null) {
                $title .= '['.$author.']';
            }

            $content = Format::formatComment((string) ($row['descr'] ?? ''), true, false, false, false);

            $xml .= '<item>
            <title><![CDATA['.$title.']]></title>
            <link>'.$itemurl.'</link>
            <description><![CDATA['.$content.']]></description>
            <author>'.$author.'@'.$httpHost.' ('.$author.')</author>
            <category domain="'.$baseUrl.'/torrents.php?cat='.(int) ($row['category'] ?? 0).'">'.($row['category_name'] ?? '').'</category>
            <comments><![CDATA['.$baseUrl.'/details.php?id='.(int) ($row['id'] ?? 0).'&cmtpage=0#startcomments]]></comments>
            <enclosure url="'.$itemdlurl.'" length="'.(int) ($row['size'] ?? 0).'" type="application/x-bittorrent" />
            <guid isPermaLink="false">'.preg_replace_callback('/./s', $hexEsc, Strings::padHash((string) ($row['info_hash'] ?? ''))).'</guid>
            <pubDate>'.date('r', strtotime((string) ($row['added'] ?? 'now')) ?: time()).'</pubDate>
        </item>
';
        }

        $xml .= '</channel>
</rss>';

        Log::writeWithContext('rss cache generated', 'info');
        NexusDB::cache_put($cacheKey, $xml, 300);

        return response($xml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }

    public function delete(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/delete.php'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);

        if ($request->query('id') !== null) {
            return $this->legacyAbortResponse('Party is over!', "This trick doesn't work anymore. You need to click the button!");
        }

        $id = SupportContext::getPost('id');
        $lang = (array) SupportContext::getGlobal('lang_delete', []);

        if ($id === null) {
            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['std_missing_form_date'] ?? 'Missing form data');
        }

        $id = (int) $id;
        if ($id <= 0) {
            return $this->legacyPage($request, 'delete', true);
        }

        if (! Permissions::userCan(PermissionEnum::TORRENT_DELETE->value, false, $currentUserId)) {
            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['std_not_owner'] ?? 'Not owner.');
        }

        $torrent = Torrent::query()->find($id, ['name', 'owner', 'seeders', 'anonymous']);
        if ($torrent === null) {
            return $this->legacyPage($request, 'delete', true);
        }
        $row = $torrent->toArray();

        if ($currentUserId != $row['owner'] && ! Permissions::userCan(PermissionEnum::TORRENT_MANAGE->value, false, $currentUserId)) {
            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['std_not_owner'] ?? 'Not owner.');
        }

        $rt = (int) SupportContext::getPost('reasontype');
        if ($rt < 1 || $rt > 5) {
            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', ($lang['std_invalid_reason'] ?? 'Invalid reason: ').$rt.'.');
        }

        $reason = (array) SupportContext::getPost('reason');
        if ($rt == 1) {
            $reasonstr = 'Dead: 0 seeders, 0 leechers = 0 peers total';
        } elseif ($rt == 2) {
            $reasonstr = 'Dupe'.(! empty($reason[0]) ? ': '.trim($reason[0]) : '!');
        } elseif ($rt == 3) {
            $reasonstr = 'Nuked'.(! empty($reason[1]) ? ': '.trim($reason[1]) : '!');
        } elseif ($rt == 4) {
            if (empty($reason[2])) {
                return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['std_describe_violated_rule'] ?? 'Describe violated rule.');
            }
            $siteName = (string) SupportContext::getGlobal('SITENAME', '');
            $reasonstr = $siteName.' rules broken: '.trim($reason[2]);
        } else {
            if (empty($reason[3])) {
                return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['std_enter_reason'] ?? 'Enter reason.');
            }
            $reasonstr = trim($reason[3]);
        }

        $searchRep = new SearchRepository;
        if ($searchRep->deleteTorrent($id) === false) {
            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', 'Delete es fail.');
        }

        TorrentOps::deleteTorrents($id, false);

        if ($row['anonymous'] === 'yes' && $currentUserId == $row['owner']) {
            Log::writeWithContext("Torrent $id ({$row['name']}) was deleted by its anonymous uploader ($reasonstr)", 'normal');
        } else {
            Log::writeWithContext("Torrent $id ({$row['name']}) was deleted by {$curUser['username']} ($reasonstr)", 'normal');
        }

        $uploadtorrentBonus = (float) SupportContext::getGlobal('uploadtorrent_bonus', 0);
        Bonus::updatePoints('-', $uploadtorrentBonus, (int) $row['owner']);

        if ($currentUserId != $row['owner'] && User::query()->where('id', $row['owner'])->exists()) {
            $dt = date('Y-m-d H:i:s');
            $locale = Locale::userLocale((int) $row['owner']);
            $subject = Locale::trans('torrent.msg_torrent_deleted', [], $locale);
            $msg = Locale::trans('torrent.msg_the_torrent_you_uploaded', [], $locale)
                .$row['name']
                .Locale::trans('torrent.msg_was_deleted_by', [], $locale)
                ."[url=userdetails.php?id=$currentUserId]{$curUser['username']}[/url]"
                .Locale::trans('torrent.msg_reason_is', [], $locale)
                .$reasonstr;
            Message::add([
                'sender' => 0,
                'receiver' => $row['owner'],
                'subject' => $subject,
                'msg' => $msg,
                'added' => $dt,
            ]);
        }

        $returnto = (string) SupportContext::getPost('returnto');
        if ($returnto !== '') {
            $ret = '<a href="'.htmlspecialchars($returnto).'">'.($lang['text_go_back'] ?? 'Go back').'</a>';
        } else {
            $ret = '<a href="index.php">'.($lang['text_back_to_index'] ?? 'Back to index').'</a>';
        }

        return $this->legacyPage($request, 'delete', true, [
            'ret' => $ret,
            'message' => $lang['text_torrent_deleted'] ?? 'Torrent deleted.',
        ]);
    }

    public function downloadnotice(Request $request): Response|RedirectResponse|View
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/downloadnotice.php'.($qs ? '?'.$qs : ''));
        }

        if ($request->isMethod('POST')) {
            $torrentid = (int) $request->input('id', 0);
            $type = $request->input('type');
            $hidenotice = $request->input('hidenotice');
            if (! $torrentid || ! in_array($type, ['firsttime', 'client', 'ratio'], true)) {
                return response('error');
            }

            $userId = (int) ($curUser['id'] ?? 0);
            if ($hidenotice && $userId > 0) {
                $update = [];
                if ($type === 'firsttime') {
                    $update['showdlnotice'] = 0;
                } elseif ($type === 'client') {
                    $update['showclienterror'] = 'no';
                }
                if (! empty($update)) {
                    User::query()->where('id', $userId)->update($update);
                }
            }

            return redirect('/download?id='.$torrentid.'&letdown=1');
        }

        $torrentid = (int) $request->input('torrentid');
        $type = $request->input('type');
        $lang = (array) (SupportContext::getGlobal('lang_downloadnotice') ?? []);
        $timenow = time();

        switch ($type) {
            case 'client':
                $title = $lang['text_client_banned_notice'] ?? '';
                $note = $lang['text_client_banned_note'] ?? '';
                $noticenexttime = $lang['text_notice_not_show_again'] ?? '';
                $showrationotice = false;
                $showclientnotice = true;
                $forcecheck = false;
                break;
            case 'ratio':
                $leechwarnuntiltime = strtotime((string) ($curUser['leechwarnuntil'] ?? ''));
                $note = '';
                if ($leechwarnuntiltime && $timenow < $leechwarnuntiltime) {
                    $kicktimeout = Time::format($curUser['leechwarnuntil'], false, false, true);
                    $note = ($lang['text_low_ratio_note_one'] ?? '').$kicktimeout.($lang['text_low_ratio_note_two'] ?? '');
                }
                $title = $lang['text_low_ratio_notice'] ?? '';
                $noticenexttime = $lang['text_notice_always_show'] ?? '';
                $showrationotice = true;
                $showclientnotice = false;
                $forcecheck = true;
                break;
            case 'firsttime':
            default:
                $type = 'firsttime';
                $title = $lang['text_first_time_download_notice'] ?? '';
                $note = $lang['text_first_time_download_note'] ?? '';
                $noticenexttime = $lang['text_notice_not_show_again'] ?? '';
                $showrationotice = true;
                $showclientnotice = true;
                $forcecheck = false;
        }

        $tdattr = ($showrationotice && $showclientnotice) ? 'width="50%"' : 'colspan="2" width="100%"';

        return $this->legacyPage($request, 'downloadnotice', true, [
            'torrentid' => $torrentid,
            'type' => $type,
            'title' => $title,
            'note' => $note,
            'noticenexttime' => $noticenexttime,
            'showrationotice' => $showrationotice,
            'showclientnotice' => $showclientnotice,
            'forcecheck' => $forcecheck,
            'tdattr' => $tdattr,
            'lang_downloadnotice' => $lang,
        ]);
    }

    public function emailGateway(Request $request): Response
    {
        return response('');
    }

    public function thanks(Request $request): Response|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            return redirect('/thanks.php'.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
        }

        $curUser = SupportContext::getUser();
        $userid = (int) ($curUser['id'] ?? 0);

        if ($request->query('id') !== null) {
            LegacyResponse::abort('Party is over!', "This trick doesn't work anymore. You need to click the button!");
        }

        $torrentid = (int) SupportContext::getPost('id');
        $torrentowner = Torrent::query()->where('id', $torrentid)->value('owner');
        if (! $torrentowner) {
            LegacyResponse::abort('Error', 'Invalid torrent id!');
        }

        $existing = NexusDB::table('thanks')
            ->where('torrentid', $torrentid)
            ->where('userid', $userid)
            ->count();
        if ($existing != 0) {
            LegacyResponse::abort('Error', 'You already said thanks!');
        }

        NexusDB::table('thanks')->insert([
            'torrentid' => $torrentid,
            'userid' => $userid,
        ]);

        $saythanksBonus = (float) SupportContext::getGlobal('saythanks_bonus', 0);
        $receivethanksBonus = (float) SupportContext::getGlobal('receivethanks_bonus', 0);
        Bonus::updatePoints('+', $saythanksBonus, $userid);
        Bonus::updatePoints('+', $receivethanksBonus, (int) $torrentowner);

        return $this->legacyPageRaw($request, 'thanks', true, [
            'torrentid' => $torrentid,
            'message' => 'Thank you has been recorded.',
        ]);
    }
}
