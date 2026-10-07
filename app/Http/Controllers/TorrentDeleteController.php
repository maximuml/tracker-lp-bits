<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\DeleteTorrentRequest;
use App\Http\Requests\FastDeleteTorrentRequest;
use App\Models\Torrent;
use App\Repositories\MessageRepository;
use App\Services\PermissionChecker;
use App\Support\Bonus;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Locale;
use App\Support\Log;
use App\Support\TorrentOps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TorrentDeleteController extends LegacyController
{
    public function __construct(private readonly PermissionChecker $permissionChecker, private readonly MessageRepository $messageRepository,
        private readonly CurrentUser $currentUser,
        private readonly TorrentRepositoryInterface $torrentRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function fastDelete(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/torrents/fast-delete'.$suffix, 308);
    }

    public function fastDeleteTorrent(FastDeleteTorrentRequest $request): Response|RedirectResponse
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/fastdelete'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($this->currentUser->id());

        $id = (int) request()->input('id');
        if ($id <= 0) {
            return $this->legacyAbortResponse(__('fastdelete.std_delete_failed'), __('fastdelete.std_missing_form_data'));
        }

        if (! $this->permissionChecker->userCan(PermissionEnum::TORRENT_MANAGE->value, false, $currentUserId)
            || ! $this->permissionChecker->userCan(PermissionEnum::TORRENT_DELETE->value, false, $currentUserId)) {
            return $this->legacyAbortResponse(__('fastdelete.std_delete_failed'), __('fastdelete.text_no_permission'));
        }

        $torrent = $this->torrentRepository->findById($id, ['name', 'owner', 'seeders', 'anonymous']);
        if (! $torrent instanceof Torrent) {
            return redirect('/web/torrents');
        }
        $row = $torrent->toArray();
        $ownerId = (int) ($row['owner'] ?? 0);
        $name = (string) ($row['name'] ?? '');
        $anonymous = (int) ($row['anonymous'] ?? 0);

        $sure = request()->input('sure');
        if (empty($sure)) {
            return $this->legacyAbortResponse(
                __('fastdelete.std_delete_torrent'),
                view('fastdelete.confirm-form', ['id' => $id])->render(),
                false
            );
        }

        TorrentOps::deleteTorrents($id, false);

        $uploadtorrentBonus = (float) SiteConfig::current()->bonus->uploadTorrent();
        Bonus::updatePoints('-', $uploadtorrentBonus, $ownerId);

        if ($anonymous == 1 && $currentUserId == $ownerId) {
            Log::writeWithContext("Torrent $id ({$name}) was deleted by its anonymous uploader", 'normal');
        } else {
            Log::writeWithContext("Torrent $id ({$name}) was deleted by {$this->currentUser->username()}", 'normal');
        }

        if ($currentUserId != $ownerId && $this->userRepository->existsById($ownerId)) {
            $locale = Locale::userLocale($ownerId);
            $dt = date('Y-m-d H:i:s');
            $subject = Locale::trans('torrent.msg_torrent_deleted', [], $locale);
            $msg = Locale::trans('torrent.msg_the_torrent_you_uploaded', [], $locale)
                .$name
                .Locale::trans('torrent.msg_was_deleted_by', ['admin' => $this->currentUser->username()], $locale);
            $this->messageRepository->add([
                'sender' => null,
                'receiver' => $ownerId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => $dt,
            ]);
        }

        return redirect('/web/torrents');
    }

    public function delete(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/torrents/delete'.$suffix, 308);
    }

    public function deleteTorrent(DeleteTorrentRequest $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/delete'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($this->currentUser->id());

        if ($request->query('id') !== null) {
            return $this->legacyAbortResponse('Party is over!', "This trick doesn't work anymore. You need to click the button!");
        }

        $id = request()->post('id');
        if ($id === null) {
            return $this->legacyAbortResponse(__('delete.std_delete_failed'), __('delete.std_missing_form_date'));
        }

        $id = (int) $id;
        if ($id <= 0) {
            return $this->legacyPage($request, 'delete', true);
        }

        if (! $this->permissionChecker->userCan(PermissionEnum::TORRENT_DELETE->value, false, $currentUserId)) {
            return $this->legacyAbortResponse(__('delete.std_delete_failed'), __('delete.std_not_owner'));
        }

        $torrent = $this->torrentRepository->findById($id, ['name', 'owner', 'seeders', 'anonymous']);
        if ($torrent === null) {
            return $this->legacyPage($request, 'delete', true);
        }
        $row = $torrent->toArray();
        $ownerId = (int) ($row['owner'] ?? 0);
        $name = (string) ($row['name'] ?? '');
        $anonymous = (int) ($row['anonymous'] ?? 0);

        if ($currentUserId != $ownerId && ! $this->permissionChecker->userCan(PermissionEnum::TORRENT_MANAGE->value, false, $currentUserId)) {
            return $this->legacyAbortResponse(__('delete.std_delete_failed'), __('delete.std_not_owner'));
        }

        $rt = (int) request()->post('reasontype');
        if ($rt < 1 || $rt > 5) {
            return $this->legacyAbortResponse(__('delete.std_delete_failed'), (__('delete.std_invalid_reason')).$rt.'.');
        }

        $reason = (array) request()->post('reason');
        if ($rt == 1) {
            $reasonstr = 'Dead: 0 seeders, 0 leechers = 0 peers total';
        } elseif ($rt == 2) {
            $reasonstr = 'Dupe'.(! empty($reason[0]) ? ': '.trim($reason[0]) : '!');
        } elseif ($rt == 3) {
            $reasonstr = 'Nuked'.(! empty($reason[1]) ? ': '.trim($reason[1]) : '!');
        } elseif ($rt == 4) {
            if (empty($reason[2])) {
                return $this->legacyAbortResponse(__('delete.std_delete_failed'), __('delete.std_describe_violated_rule'));
            }
            $siteName = SiteConfig::current()->basic->siteName();
            $reasonstr = $siteName.' rules broken: '.trim($reason[2]);
        } else {
            if (empty($reason[3])) {
                return $this->legacyAbortResponse(__('delete.std_delete_failed'), __('delete.std_enter_reason'));
            }
            $reasonstr = trim($reason[3]);
        }

        TorrentOps::deleteTorrents($id, false);

        if ($anonymous == 1 && $currentUserId == $ownerId) {
            Log::writeWithContext("Torrent $id ({$name}) was deleted by its anonymous uploader ($reasonstr)", 'normal');
        } else {
            Log::writeWithContext("Torrent $id ({$name}) was deleted by {$this->currentUser->username()} ($reasonstr)", 'normal');
        }

        $uploadtorrentBonus = (float) SiteConfig::current()->bonus->uploadTorrent();
        Bonus::updatePoints('-', $uploadtorrentBonus, $ownerId);

        if ($currentUserId != $ownerId && $this->userRepository->existsById($ownerId)) {
            $dt = date('Y-m-d H:i:s');
            $locale = Locale::userLocale($ownerId);
            $subject = Locale::trans('torrent.msg_torrent_deleted', [], $locale);
            $msg = Locale::trans('torrent.msg_the_torrent_you_uploaded', [], $locale)
                .$name
                .Locale::trans('torrent.msg_was_deleted_by', [], $locale)
                ."[url=/userdetails?id=$currentUserId]{$this->currentUser->username()}[/url]"
                .Locale::trans('torrent.msg_reason_is', [], $locale)
                .$reasonstr;
            $this->messageRepository->add([
                'sender' => null,
                'receiver' => $ownerId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => $dt,
            ]);
        }

        return $this->legacyPage($request, 'delete', true, [
            'returnto' => (string) request()->post('returnto'),
            'message' => __('delete.text_torrent_deleted'),
        ]);
    }
}
