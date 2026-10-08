<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Http\Requests\FlushTorrentRequest;
use App\Http\Requests\ReseedTorrentRequest;
use App\Models\Torrent;
use App\Repositories\MessageRepository;
use App\Repositories\PeerRepository;
use App\Repositories\TorrentAjaxRepository;
use App\Services\PermissionChecker;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\Support\Path;
use App\Support\RequestValues;
use App\Support\Time;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Rhilip\Bencode\Bencode;

class TorrentMaintenanceController extends LegacyController
{
    public function __construct(private readonly TorrentAjaxRepository $torrentAjaxRepository, private readonly PermissionChecker $permissionChecker, private readonly MessageRepository $messageRepository, private readonly PeerRepository $peerRepository, private readonly TorrentRepositoryInterface $torrentRepository,
        private readonly CurrentUser $currentUser,
    ) {}

    public function torrentInfo(Request $request): View|RedirectResponse|Response
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            abort(404);
        }

        $torrent = $this->torrentRepository->findById($id, ['id', 'name']);
        if (! $torrent instanceof Torrent) {
            abort(404);
        }

        $curUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($this->currentUser->id());
        if (! $this->permissionChecker->userCan(PermissionEnum::TORRENT_STRUCTURE->value, false, $currentUserId)) {
            abort(403);
        }

        $torrentDir = SiteConfig::current()->main->torrentDir();
        $filePath = Path::resolve("{$torrentDir}/{$id}.torrent", \ROOT_PATH);
        if (! is_file($filePath) || ! is_readable($filePath)) {
            abort(404);
        }

        $dict = Bencode::load($filePath);

        return $this->renderPage($request, 'torrent_info', true, [
            'torrentName' => (string) $torrent->name,
            'structureHtml' => SafeHtml::fromTrustedHtml($this->torrentStructureBuilder(['root' => $dict])),
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
        return view('components.torrent-structure', ['items' => $this->torrentStructureNodes($array, $parent)])->render();
    }

    /**
     * @param  array<string|int, mixed>  $array
     * @return list<array{item: string|int, type: string, length: int, value: mixed, children: list<array{item: string|int, type: string, length: int, value: mixed, children: mixed}>|null}>
     */
    private function torrentStructureNodes(array $array, string $parent = ''): array
    {
        $nodes = [];
        foreach ($array as $item => $value) {
            $nodes[] = [
                'item' => $item,
                'type' => is_iterable($value)
                    ? ($this->isIndexedArray(is_array($value) ? $value : iterator_to_array($value)) ? 'list' : 'dictionary')
                    : (is_int($value) ? 'integer' : 'string'),
                'length' => strlen(Bencode::encode($value)),
                'value' => ($parent === 'info' && $item === 'pieces') ? '0x'.bin2hex(substr((string) $value, 0, 25)).'...' : $value,
                'children' => is_iterable($value)
                    ? $this->torrentStructureNodes(is_array($value) ? $value : iterator_to_array($value), (string) $item)
                    : null,
            ];
        }

        return $nodes;
    }

    public function flush(FlushTorrentRequest $request): Response|RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return $this->abortResponse('Error', 'Invalid ID.');
        }

        $currentUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($this->currentUser->id());
        $currentClass = (int) UserDisplay::currentClass();

        if ($currentClass >= UserClassEnum::MODERATOR->value || $currentUserId === $id) {
            $deadtime = Time::deadThreshold(SiteConfig::current()->main->anninterthree());
            $lastAction = date('Y-m-d H:i:s', $deadtime);
            $effected = $this->peerRepository->deleteInactiveForUser($id, $lastAction);

            return $this->abortResponse(
                __('takeflush.std_success'),
                $effected.' '.(__('takeflush.std_ghost_torrents_cleaned'))
            );
        }

        return $this->abortResponse(
            __('takeflush.std_failed'),
            __('takeflush.std_cannot_flush_others')
        );
    }

    public function reseed(ReseedTorrentRequest $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/takereseed.php'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($this->currentUser->id());
        if (! $this->permissionChecker->userCan(PermissionEnum::ASK_RESEED->value, false, $currentUserId)) {
            return $this->abortResponse(__('takereseed.std_error'), ('Permission denied.'));
        }

        $reseedid = (int) (request()->query('reseedid') ?? request()->query('id') ?? 0);
        $torrent = $this->torrentRepository->findById($reseedid);
        $row = $torrent instanceof Torrent ? $torrent->toArray() : null;

        $seederCount = $this->peerRepository->countForTorrent($reseedid);
        if ($seederCount > 0) {
            return $this->abortResponse(__('takereseed.std_error'), __('takereseed.std_torrent_not_dead'));
        }

        $timeNow = defined('TIMENOW') ? (int) constant('TIMENOW') : time();
        if ($row !== null && strtotime((string) ($row['last_reseed'] ?? '')) > ($timeNow - 900)) {
            return $this->abortResponse(__('takereseed.std_error'), __('takereseed.std_reseed_sent_recently'));
        }

        $snatchedRows = $this->torrentAjaxRepository->listFinishedSnatchersForReseed($reseedid);

        $baseUrl = SiteConfig::current()->basic->baseUrl() ?: RequestValues::serverValue('HTTP_HOST', 'localhost');
        foreach ($snatchedRows as $snatchRow) {
            $locale = Locale::userLocale((int) $snatchRow['userid']);
            $rsSubject = Locale::trans('torrent.msg_reseed_request', [], $locale);
            $pnMsg = Locale::trans('torrent.msg_reseed_user', [], $locale)
                .$this->currentUser->username()
                .Locale::trans('torrent.msg_ask_reseed', [], $locale)
                .'[url='.Url::absolute($baseUrl).'/web/details/'.$reseedid.']'.$snatchRow['torrent_name'].'[/url]'
                .Locale::trans('torrent.msg_thank_you', [], $locale);
            $this->messageRepository->add([
                'sender' => null,
                'receiver' => $snatchRow['userid'],
                'subject' => $rsSubject,
                'msg' => $pnMsg,
                'added' => now(),
            ]);
        }

        $this->torrentRepository->updateFields($reseedid, [
            'last_reseed' => now(),
            'seeders' => $seederCount,
        ]);

        return $this->renderPage($request, 'takereseed', true, [
            'message' => __('takereseed.std_it_worked'),
        ]);
    }
}
