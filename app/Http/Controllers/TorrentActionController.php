<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Invite;
use App\Models\Message;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\SearchRepository;
use App\Repositories\TorrentAjaxRepository;
use App\Support\Bonus;
use App\Support\Http;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\TorrentOps;
use App\Support\Url;
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
            'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
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

        $searchRep = new SearchRepository();
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
        if ($cache !== null && method_exists($cache, 'delete_value')) {
            $cache->delete_value('user_' . $userId . '_bookmark_array');
        }

        return response($status, 200, $headers);
    }

    public function fastDelete(Request $request): Response|RedirectResponse
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            $qs = $request->getQueryString();
            return redirect('/fastdelete.php' . ($qs ? '?' . $qs : ''));
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
                ($lang['std_delete_torrent_note'] ?? '') . "<a class=altlink href=fastdelete.php?id=$id&sure=1>" . ($lang['std_here_if_sure'] ?? 'here') . '</a>',
                false
            );
        }

        $searchRep = new SearchRepository();
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
                . $row['name']
                . Locale::trans('torrent.msg_was_deleted_by', ['admin' => $curUser['username']], $locale);
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

        $torrentDir = \App\Support\Config\SiteConfig::current()->main->torrentDir();
        $filePath = \App\Support\Path::resolve("{$torrentDir}/{$id}.torrent", \ROOT_PATH);
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
            'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
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
        $currentUser = ! empty($curUser) ? User::query()->find($curUser['id'] ?? 0) : null;

        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
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

    public function takeFlush(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'takeflush');
    }

    public function takeReseed(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            $qs = $request->getQueryString();
            return redirect('/takereseed.php' . ($qs ? '?' . $qs : ''));
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
                . $curUser['username']
                . Locale::trans('torrent.msg_ask_reseed', [], $locale)
                . '[url=' . Http::protocolPrefix(Url::isSecure()) . $baseUrl . '/details.php?id=' . $reseedid . ']' . $snatchRow['torrent_name'] . '[/url]'
                . Locale::trans('torrent.msg_thank_you', [], $locale);
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
        $currentUser = ! empty($curUser) ? User::query()->find($curUser['id'] ?? 0) : null;

        if ($currentUser === null || (! Permissions::userCan(PermissionEnum::TORRENT_HISTORY->value, false, $currentUser->id) && $currentUser->id !== $targetUserId)) {
            return response('', 403, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $page = (int) $request->input('page', 0);

        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
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
            return response(json_encode([], JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        return response(
            json_encode(TorrentAjaxRepository::searchSuggest($searchstr), JSON_UNESCAPED_UNICODE),
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

    public function torrentrss(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'torrentrss', false);
    }

    public function delete(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            $qs = $request->getQueryString();
            return redirect('/delete.php' . ($qs ? '?' . $qs : ''));
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
            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', ($lang['std_invalid_reason'] ?? 'Invalid reason: ') . $rt . '.');
        }

        $reason = (array) SupportContext::getPost('reason');
        if ($rt == 1) {
            $reasonstr = 'Dead: 0 seeders, 0 leechers = 0 peers total';
        } elseif ($rt == 2) {
            $reasonstr = 'Dupe' . (! empty($reason[0]) ? ': ' . trim($reason[0]) : '!');
        } elseif ($rt == 3) {
            $reasonstr = 'Nuked' . (! empty($reason[1]) ? ': ' . trim($reason[1]) : '!');
        } elseif ($rt == 4) {
            if (empty($reason[2])) {
                return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['std_describe_violated_rule'] ?? 'Describe violated rule.');
            }
            $siteName = (string) SupportContext::getGlobal('SITENAME', '');
            $reasonstr = $siteName . ' rules broken: ' . trim($reason[2]);
        } else {
            if (empty($reason[3])) {
                return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['std_enter_reason'] ?? 'Enter reason.');
            }
            $reasonstr = trim($reason[3]);
        }

        $searchRep = new SearchRepository();
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
                . $row['name']
                . Locale::trans('torrent.msg_was_deleted_by', [], $locale)
                . "[url=userdetails.php?id=$currentUserId]{$curUser['username']}[/url]"
                . Locale::trans('torrent.msg_reason_is', [], $locale)
                . $reasonstr;
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
            $ret = '<a href="' . htmlspecialchars($returnto) . '">' . ($lang['text_go_back'] ?? 'Go back') . '</a>';
        } else {
            $ret = '<a href="index.php">' . ($lang['text_back_to_index'] ?? 'Back to index') . '</a>';
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
            return redirect('/downloadnotice.php' . ($qs ? '?' . $qs : ''));
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

            return redirect('/download?id=' . $torrentid . '&letdown=1');
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
                    $kicktimeout = \App\Support\Time::format($curUser['leechwarnuntil'], false, false, true);
                    $note = ($lang['text_low_ratio_note_one'] ?? '') . $kicktimeout . ($lang['text_low_ratio_note_two'] ?? '');
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

    public function emailGateway(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'email-gateway', false);
    }

    public function thanks(Request $request): Response|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            return redirect('/thanks.php' . ($request->getQueryString() ? '?' . $request->getQueryString() : ''));
        }

        $curUser = SupportContext::getUser();
        $userid = (int) ($curUser['id'] ?? 0);

        if ($request->query('id') !== null) {
            \App\Support\LegacyResponse::abort('Party is over!', "This trick doesn't work anymore. You need to click the button!");
        }

        $torrentid = (int) SupportContext::getPost('id');
        $torrentowner = Torrent::query()->where('id', $torrentid)->value('owner');
        if (! $torrentowner) {
            \App\Support\LegacyResponse::abort('Error', 'Invalid torrent id!');
        }

        $existing = NexusDB::table('thanks')
            ->where('torrentid', $torrentid)
            ->where('userid', $userid)
            ->count();
        if ($existing != 0) {
            \App\Support\LegacyResponse::abort('Error', 'You already said thanks!');
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
