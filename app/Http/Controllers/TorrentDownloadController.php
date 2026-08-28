<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\NexusException;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\IpLogRepository;
use App\Repositories\TorrentRepository;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Http;
use App\Support\Json;
use App\Support\Logger;
use App\Support\Network;
use App\Support\Path;
use App\Support\Time;
use App\Support\Tracker;
use App\Support\Url;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Rhilip\Bencode\TorrentFile;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TorrentDownloadController extends LegacyController
{
    public function download(Request $request, TorrentRepository $torrentRepository): SymfonyResponse
    {
        $downhash = $request->downhash;
        $passkey = $request->passkey;
        $id = (int) $request->id;

        if (! empty($downhash)) {
            $params = explode('.', $downhash, 2);
            if (empty($params[0]) || empty($params[1])) {
                throw new NexusException('download.invalid_downhash_format');
            }
            $uid = (int) $params[0];
            $hash = $params[1];
            $user = User::query()->find($uid);
            if (! $user) {
                throw new NexusException('download.invalid_uid');
            }
            if ($user->enabled == 'no' || $user->parked == 'yes') {
                throw new NexusException('download.account_disabled_or_parked');
            }
            $decrypted = $torrentRepository->decryptDownHash($hash, $user);
            if (empty($decrypted)) {
                Logger::writeWithContext((string) ('downhash invalid: '.Json::encode($request->all())), (string) 'error', (bool) false);
                throw new NexusException('download.invalid_downhash_decrypt');
            }
            $id = (int) $decrypted[0];
        } elseif (SiteConfig::current()->torrent->downloadSupportPasskey() && ! empty($passkey) && ! empty($id)) {
            $user = User::query()->where('passkey', $passkey)->first();
            if (! $user) {
                throw new NexusException('download.invalid_passkey');
            }
            if ($user->enabled == 'no' || $user->parked == 'yes') {
                throw new NexusException('download.account_disabled_or_parked');
            }
        } else {
            if (! $id) {
                abort(404);
            }
            $user = Auth::guard('nexus-web')->user();
            if (! $user instanceof User) {
                return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
            }
            if ($user->parked == 'yes') {
                throw new NexusException('download.account_parked');
            }
            if (! $request->letdown) {
                if ($user->showclienterror == 'yes') {
                    return redirect('/downloadnotice.php?torrentid='.$id.'&type=client');
                }
                if ($user->leechwarn == 'yes') {
                    return redirect('/downloadnotice.php?torrentid='.$id.'&type=ratio');
                }
            }
        }

        if (! $user instanceof User) {
            throw new NexusException('download.invalid_user');
        }

        $ip = Network::clientIp();
        User::query()->where('id', $user->id)->update([
            'last_access' => now()->toDateTimeString(),
            'ip' => $ip,
        ]);
        IpLogRepository::saveToCache($user->id, $request->getPathInfo(), [$ip]);

        $torrent = Torrent::query()->findOrFail($id);

        Gate::forUser($user)->authorize('download', $torrent);

        if (strlen((string) $user->passkey) != 32) {
            $passkey = md5($user->username.date('Y-m-d H:i:s').$user->passhash);
            User::query()->where('id', $user->id)->update(['passkey' => $passkey]);
            $user->passkey = $passkey;
        }

        $torrentSavePath = Path::resolve(SiteConfig::current()->main->torrentDir(), \ROOT_PATH);
        $fn = $torrentSavePath.'/'.$torrent->id.'.torrent';
        if (! is_file($fn) || ! is_readable($fn) || filesize($fn) == 0) {
            abort(404);
        }

        $dict = TorrentFile::load($fn);
        $trackerHost = Tracker::schemaAndHost((int) $user->tracker_url_id, true);
        if (is_array($trackerHost)) {
            $trackerHost = ($trackerHost['scheme'] ?? '').'://'.($trackerHost['host'] ?? '');
        }
        $dict->cleanRootFields()
            ->setAnnounce($trackerHost.'?passkey='.(string) $user->passkey)
            ->setComment(Url::schemeAndHost(true).'/details.php?id='.$torrent->id)
            ->setCreatedBy(SiteConfig::current()->basic->siteName())
            ->setCreationDate($torrent->added?->getTimestamp() ?? time());

        $torrent->increment('hits');

        $filename = SiteConfig::current()->main->torrentNamePrefix().$torrent->save_as.'.torrent';
        $headers = [
            'Content-Type' => 'application/x-bittorrent',
            'Content-Disposition' => Http::contentDisposition($filename, 'attachment'),
        ];

        return response($dict->dumpToString(), 200, $headers);
    }

    public function downloadnotice(Request $request): Response|RedirectResponse|View
    {
        $curUser = app(CurrentUser::class)->get();
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
        $lang = (array) (app(Globals::class)->get('lang_downloadnotice') ?? []);
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
}
