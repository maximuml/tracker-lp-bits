<?php

namespace App\Http\Controllers;

use App\Exceptions\NexusException;
use App\Models\Torrent;
use App\Models\User;
use App\Policies\TorrentPolicy;
use App\Repositories\IpLogRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\TorrentUploadRepository;
use App\Support\Http;
use App\Support\Network;
use App\Support\Tracker;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Rhilip\Bencode\TorrentFile;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TorrentDownloadController extends Controller
{
    public function download(Request $request, TorrentRepository $torrentRepository): SymfonyResponse
    {
        $downhash = $request->downhash;
        $passkey = $request->passkey;
        $id = (int) $request->id;

        if (!empty($downhash)) {
            $params = explode('.', $downhash, 2);
            if (empty($params[0]) || empty($params[1])) {
                throw new NexusException('download.invalid_downhash_format');
            }
            $uid = (int) $params[0];
            $hash = $params[1];
            $user = User::query()->find($uid);
            if (!$user) {
                throw new NexusException('download.invalid_uid');
            }
            if ($user->enabled == 'no' || $user->parked == 'yes') {
                throw new NexusException('download.account_disabled_or_parked');
            }
            $decrypted = $torrentRepository->decryptDownHash($hash, $user);
            if (empty($decrypted)) {
                do_log('downhash invalid: ' . nexus_json_encode($request->all()), 'error');
                throw new NexusException('download.invalid_downhash_decrypt');
            }
            $id = (int) $decrypted[0];
        } elseif (\App\Support\Config\SiteConfig::current()->torrent->downloadSupportPasskey() && !empty($passkey) && !empty($id)) {
            $user = User::query()->where('passkey', $passkey)->first();
            if (!$user) {
                throw new NexusException('download.invalid_passkey');
            }
            if ($user->enabled == 'no' || $user->parked == 'yes') {
                throw new NexusException('download.account_disabled_or_parked');
            }
        } else {
            if (!$id) {
                abort(404);
            }
            $user = Auth::guard('nexus-web')->user();
            if (!$user instanceof User) {
                return redirect('/login.php?returnto=' . urlencode($request->fullUrl()));
            }
            if ($user->parked == 'yes') {
                throw new NexusException('download.account_parked');
            }
            if (!$request->letdown) {
                if ($user->showclienterror == 'yes') {
                    return redirect('/downloadnotice.php?torrentid=' . $id . '&type=client');
                }
                if ($user->leechwarn == 'yes') {
                    return redirect('/downloadnotice.php?torrentid=' . $id . '&type=ratio');
                }
            }
        }

        if (!$user instanceof User) {
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

        if (strlen($user->passkey) != 32) {
            $passkey = md5($user->username . date('Y-m-d H:i:s') . $user->passhash);
            User::query()->where('id', $user->id)->update(['passkey' => $passkey]);
            $user->passkey = $passkey;
        }

        $torrentSavePath = getFullDirectory(\App\Support\Config\SiteConfig::current()->main->torrentDir());
        $fn = $torrentSavePath . '/' . $torrent->id . '.torrent';
        if (!is_file($fn) || !is_readable($fn) || filesize($fn) == 0) {
            abort(404);
        }

        $dict = TorrentFile::load($fn);
        $dict->cleanRootFields()
            ->setAnnounce(Tracker::schemaAndHost($user->tracker_url_id, true) . '?passkey=' . $user->passkey)
            ->setComment(Url::schemeAndHost(true) . '/details.php?id=' . $torrent->id)
            ->setCreatedBy(\App\Support\Config\SiteConfig::current()->basic->siteName())
            ->setCreationDate(strtotime($torrent->added));

        $torrent->increment('hits');

        $filename = \App\Support\Config\SiteConfig::current()->main->torrentNamePrefix() . $torrent->save_as . '.torrent';
        $headers = [
            'Content-Type' => 'application/x-bittorrent',
            'Content-Disposition' => Http::contentDisposition($filename, 'attachment'),
        ];

        return response($dict->dumpToString(), 200, $headers);
    }
}
