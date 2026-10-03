<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Bonus;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Permissions;
use App\Support\TorrentOps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TorrentDeleteController extends LegacyController
{
    public function __construct(
        private readonly CurrentUser $currentUser,
    ) {}

    public function fastDelete(Request $request): Response|RedirectResponse
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/fastdelete.php'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);

        $id = (int) request()->input('id');
        if ($id <= 0) {
            return $this->legacyAbortResponse(__('legacy/fastdelete.std_delete_failed'), __('legacy/fastdelete.std_missing_form_data'));
        }

        if (! Permissions::userCan(PermissionEnum::TORRENT_MANAGE->value, false, $currentUserId)
            || ! Permissions::userCan(PermissionEnum::TORRENT_DELETE->value, false, $currentUserId)) {
            return $this->legacyAbortResponse(__('legacy/fastdelete.std_delete_failed'), __('legacy/fastdelete.text_no_permission'));
        }

        $torrent = Torrent::query()->where('id', $id)->first(['name', 'owner', 'seeders', 'anonymous']);
        if (! $torrent instanceof Torrent) {
            return redirect('/torrents.php');
        }
        $row = $torrent->toArray();
        $ownerId = (int) ($row['owner'] ?? 0);
        $name = (string) ($row['name'] ?? '');
        $anonymous = (int) ($row['anonymous'] ?? 0);

        $sure = request()->query('sure');
        if (empty($sure)) {
            return $this->legacyAbortResponse(
                __('legacy/fastdelete.std_delete_torrent'),
                (__('legacy/fastdelete.std_delete_torrent_note'))."<a class=altlink href=fastdelete.php?id=$id&sure=1> ".__('legacy/fastdelete.std_here').'</a>'.__('legacy/fastdelete.std_if_sure'),
                false
            );
        }

        TorrentOps::deleteTorrents($id, false);

        $uploadtorrentBonus = (float) SiteConfig::current()->bonus->uploadTorrent();
        Bonus::updatePoints('-', $uploadtorrentBonus, $ownerId);

        if ($anonymous == 1 && $currentUserId == $ownerId) {
            Log::writeWithContext("Torrent $id ({$name}) was deleted by its anonymous uploader", 'normal');
        } else {
            Log::writeWithContext("Torrent $id ({$name}) was deleted by {$curUser['username']}", 'normal');
        }

        if ($currentUserId != $ownerId && User::query()->where('id', $ownerId)->exists()) {
            $locale = Locale::userLocale($ownerId);
            $dt = date('Y-m-d H:i:s');
            $subject = Locale::trans('torrent.msg_torrent_deleted', [], $locale);
            $msg = Locale::trans('torrent.msg_the_torrent_you_uploaded', [], $locale)
                .$name
                .Locale::trans('torrent.msg_was_deleted_by', ['admin' => $curUser['username']], $locale);
            Message::add([
                'sender' => null,
                'receiver' => $ownerId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => $dt,
            ]);
        }

        return redirect('/torrents.php');
    }

    public function delete(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/delete.php'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);

        if ($request->query('id') !== null) {
            return $this->legacyAbortResponse('Party is over!', "This trick doesn't work anymore. You need to click the button!");
        }

        $id = request()->post('id');
        if ($id === null) {
            return $this->legacyAbortResponse(__('legacy/delete.std_delete_failed'), __('legacy/delete.std_missing_form_date'));
        }

        $id = (int) $id;
        if ($id <= 0) {
            return $this->legacyPage($request, 'delete', true);
        }

        if (! Permissions::userCan(PermissionEnum::TORRENT_DELETE->value, false, $currentUserId)) {
            return $this->legacyAbortResponse(__('legacy/delete.std_delete_failed'), __('legacy/delete.std_not_owner'));
        }

        $torrent = Torrent::query()->find($id, ['name', 'owner', 'seeders', 'anonymous']);
        if ($torrent === null) {
            return $this->legacyPage($request, 'delete', true);
        }
        $row = $torrent->toArray();
        $ownerId = (int) ($row['owner'] ?? 0);
        $name = (string) ($row['name'] ?? '');
        $anonymous = (int) ($row['anonymous'] ?? 0);

        if ($currentUserId != $ownerId && ! Permissions::userCan(PermissionEnum::TORRENT_MANAGE->value, false, $currentUserId)) {
            return $this->legacyAbortResponse(__('legacy/delete.std_delete_failed'), __('legacy/delete.std_not_owner'));
        }

        $rt = (int) request()->post('reasontype');
        if ($rt < 1 || $rt > 5) {
            return $this->legacyAbortResponse(__('legacy/delete.std_delete_failed'), (__('legacy/delete.std_invalid_reason')).$rt.'.');
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
                return $this->legacyAbortResponse(__('legacy/delete.std_delete_failed'), __('legacy/delete.std_describe_violated_rule'));
            }
            $siteName = SiteConfig::current()->basic->siteName();
            $reasonstr = $siteName.' rules broken: '.trim($reason[2]);
        } else {
            if (empty($reason[3])) {
                return $this->legacyAbortResponse(__('legacy/delete.std_delete_failed'), __('legacy/delete.std_enter_reason'));
            }
            $reasonstr = trim($reason[3]);
        }

        TorrentOps::deleteTorrents($id, false);

        if ($anonymous == 1 && $currentUserId == $ownerId) {
            Log::writeWithContext("Torrent $id ({$name}) was deleted by its anonymous uploader ($reasonstr)", 'normal');
        } else {
            Log::writeWithContext("Torrent $id ({$name}) was deleted by {$curUser['username']} ($reasonstr)", 'normal');
        }

        $uploadtorrentBonus = (float) SiteConfig::current()->bonus->uploadTorrent();
        Bonus::updatePoints('-', $uploadtorrentBonus, $ownerId);

        if ($currentUserId != $ownerId && User::query()->where('id', $ownerId)->exists()) {
            $dt = date('Y-m-d H:i:s');
            $locale = Locale::userLocale($ownerId);
            $subject = Locale::trans('torrent.msg_torrent_deleted', [], $locale);
            $msg = Locale::trans('torrent.msg_the_torrent_you_uploaded', [], $locale)
                .$name
                .Locale::trans('torrent.msg_was_deleted_by', [], $locale)
                ."[url=userdetails.php?id=$currentUserId]{$curUser['username']}[/url]"
                .Locale::trans('torrent.msg_reason_is', [], $locale)
                .$reasonstr;
            Message::add([
                'sender' => null,
                'receiver' => $ownerId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => $dt,
            ]);
        }

        return $this->legacyPage($request, 'delete', true, [
            'returnto' => (string) request()->post('returnto'),
            'message' => __('legacy/delete.text_torrent_deleted'),
        ]);
    }
}
