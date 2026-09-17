<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Models\Message;
use App\Models\Peer;
use App\Models\Torrent;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Http;
use App\Support\Locale;
use App\Support\Path;
use App\Support\Permissions;
use App\Support\Time;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Rhilip\Bencode\Bencode;

class TorrentMaintenanceController extends LegacyController
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
    ) {}

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

        $curUser = $this->currentUser->get() ?? [];
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
            'structureHtml' => $this->torrentStructureBuilder(['root' => $dict]),
        ]);
    }

    /**
     * @param  array<string|int, mixed>  $arr
     */
    private function isIndexedArray(array $arr): bool
    {
        return count(array_filter(array_keys($arr), 'is_string')) === 0;
    }

    /**
     * @param  array<string|int, mixed>  $array
     */
    private function torrentStructureBuilder(array $array, string $parent = ''): string
    {
        $ret = '';
        foreach ($array as $item => $value) {
            $value_length = strlen(Bencode::encode($value));
            if (is_iterable($value)) {
                $type = $this->isIndexedArray(is_array($value) ? $value : iterator_to_array($value)) ? 'list' : 'dictionary';
                $ret .= "<li><div align='left' class='".$type."'><a href='#' class='js-info-toggle'> + <span class=title>[".$item."]</span> <span class='icon'>(".ucfirst($type).')</span> <span class=length>['.$value_length.']</span></a></div>';
                $ret .= "<ul class='nx-hidden'>".$this->torrentStructureBuilder(is_array($value) ? $value : iterator_to_array($value), (string) $item).'</ul></li>';
            } else {
                $type = is_int($value) ? 'integer' : 'string';
                $value = ($parent === 'info' && $item === 'pieces') ? '0x'.bin2hex(substr((string) $value, 0, 25)).'...' : $value;
                $ret .= '<li><div align=left class='.$type.'> - <span class=title>['.$item.']</span> <span class=icon>('.ucfirst($type).')</span> <span class=length>['.$value_length.']</span>: <span class=value>'.$value.'</span></div></li>';
            }
        }

        return $ret;
    }

    public function takeFlush(Request $request): Response|RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return $this->legacyAbortResponse('Error', 'Invalid ID.');
        }

        $currentUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $currentClass = (int) UserDisplay::currentClass();

        if ($currentClass >= UserClassEnum::MODERATOR->value || $currentUserId === $id) {
            $deadtime = Time::deadThreshold(SiteConfig::current()->main->anninterthree());
            $lastAction = date('Y-m-d H:i:s', $deadtime);
            $effected = Peer::query()->where('last_action', '<', $lastAction)->where('userid', $id)->delete();

            return $this->legacyAbortResponse(
                __('legacy/takeflush.std_success'),
                $effected.' '.(__('legacy/takeflush.std_ghost_torrents_cleaned'))
            );
        }

        return $this->legacyAbortResponse(
            __('legacy/takeflush.std_failed'),
            __('legacy/takeflush.std_cannot_flush_others')
        );
    }

    public function takeReseed(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/takereseed.php'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);
        if (! Permissions::userCan(PermissionEnum::ASK_RESEED->value, false, $currentUserId)) {
            return $this->legacyAbortResponse(__('legacy/takereseed.std_error'), ('Permission denied.'));
        }

        $reseedid = (int) (request()->query('reseedid') ?? request()->query('id') ?? 0);
        $torrent = Torrent::query()->find($reseedid);
        $row = $torrent instanceof Torrent ? $torrent->toArray() : null;

        $seederCount = (int) Peer::query()->where('torrent', $reseedid)->count();
        if ($seederCount > 0) {
            return $this->legacyAbortResponse(__('legacy/takereseed.std_error'), __('legacy/takereseed.std_torrent_not_dead'));
        }

        $timeNow = (int) $this->globals->get('TIMENOW', time());
        if ($row !== null && strtotime((string) ($row['last_reseed'] ?? '')) > ($timeNow - 900)) {
            return $this->legacyAbortResponse(__('legacy/takereseed.std_error'), __('legacy/takereseed.std_reseed_sent_recently'));
        }

        $snatchedRows = DB::table('snatched')
            ->join('users', 'snatched.userid', '=', 'users.id')
            ->join('torrents', 'snatched.torrentid', '=', 'torrents.id')
            ->where('snatched.finished', 1)
            ->where('snatched.torrentid', $reseedid)
            ->select('snatched.userid', 'snatched.torrentid', 'torrents.name as torrent_name', 'users.id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $baseUrl = (string) $this->globals->get('BASEURL', '');
        foreach ($snatchedRows as $snatchRow) {
            $locale = Locale::userLocale((int) $snatchRow['userid']);
            $rsSubject = Locale::trans('torrent.msg_reseed_request', [], $locale);
            $pnMsg = Locale::trans('torrent.msg_reseed_user', [], $locale)
                .$curUser['username']
                .Locale::trans('torrent.msg_ask_reseed', [], $locale)
                .'[url='.Http::protocolPrefix(Url::isSecure()).$baseUrl.'/details.php?id='.$reseedid.']'.$snatchRow['torrent_name'].'[/url]'
                .Locale::trans('torrent.msg_thank_you', [], $locale);
            Message::add([
                'sender' => null,
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
            'message' => __('legacy/takereseed.std_it_worked'),
        ]);
    }
}
