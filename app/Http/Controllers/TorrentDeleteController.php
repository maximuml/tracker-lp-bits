<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Bonus;
use App\Support\CurrentUser;
use App\Support\Globals;
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
    public function fastDelete(Request $request): Response|RedirectResponse
    {
        $curUser = app(CurrentUser::class)->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/fastdelete.php'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);

        $id = (int) request()->input('id');
        if ($id <= 0) {
            $lang = (array) app(Globals::class)->get('lang_fastdelete', []);

            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['std_missing_form_data'] ?? 'Invalid id.');
        }

        if (! Permissions::userCan(PermissionEnum::TORRENT_MANAGE->value, false, $currentUserId)
            || ! Permissions::userCan(PermissionEnum::TORRENT_DELETE->value, false, $currentUserId)) {
            $lang = (array) app(Globals::class)->get('lang_fastdelete', []);

            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['text_no_permission'] ?? 'No permission.');
        }

        $torrent = Torrent::query()->where('id', $id)->first(['name', 'owner', 'seeders', 'anonymous']);
        if (! $torrent instanceof Torrent) {
            return redirect('/torrents.php');
        }
        $row = $torrent->toArray();

        $sure = request()->query('sure');
        if (empty($sure)) {
            $lang = (array) app(Globals::class)->get('lang_fastdelete', []);

            return $this->legacyAbortResponse(
                $lang['std_delete_torrent'] ?? 'Delete torrent',
                ($lang['std_delete_torrent_note'] ?? '')."<a class=altlink href=fastdelete.php?id=$id&sure=1>".($lang['std_here_if_sure'] ?? 'here').'</a>',
                false
            );
        }

        TorrentOps::deleteTorrents($id, false);

        $uploadtorrentBonus = (float) app(Globals::class)->get('uploadtorrent_bonus', 0);
        Bonus::updatePoints('-', $uploadtorrentBonus, (int) $row['owner']);

        if ($row['anonymous'] == 1 && $currentUserId == $row['owner']) {
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
                'sender' => null,
                'receiver' => $row['owner'],
                'subject' => $subject,
                'msg' => $msg,
                'added' => $dt,
            ]);
        }

        return redirect('/torrents.php');
    }

    public function delete(Request $request): View|RedirectResponse|Response
    {
        $curUser = app(CurrentUser::class)->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/delete.php'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);

        if ($request->query('id') !== null) {
            return $this->legacyAbortResponse('Party is over!', "This trick doesn't work anymore. You need to click the button!");
        }

        $id = request()->post('id');
        $lang = (array) app(Globals::class)->get('lang_delete', []);

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

        $rt = (int) request()->post('reasontype');
        if ($rt < 1 || $rt > 5) {
            return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', ($lang['std_invalid_reason'] ?? 'Invalid reason: ').$rt.'.');
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
                return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['std_describe_violated_rule'] ?? 'Describe violated rule.');
            }
            $siteName = (string) app(Globals::class)->get('SITENAME', '');
            $reasonstr = $siteName.' rules broken: '.trim($reason[2]);
        } else {
            if (empty($reason[3])) {
                return $this->legacyAbortResponse($lang['std_delete_failed'] ?? 'Error', $lang['std_enter_reason'] ?? 'Enter reason.');
            }
            $reasonstr = trim($reason[3]);
        }

        TorrentOps::deleteTorrents($id, false);

        if ($row['anonymous'] == 1 && $currentUserId == $row['owner']) {
            Log::writeWithContext("Torrent $id ({$row['name']}) was deleted by its anonymous uploader ($reasonstr)", 'normal');
        } else {
            Log::writeWithContext("Torrent $id ({$row['name']}) was deleted by {$curUser['username']} ($reasonstr)", 'normal');
        }

        $uploadtorrentBonus = (float) app(Globals::class)->get('uploadtorrent_bonus', 0);
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
                'sender' => null,
                'receiver' => $row['owner'],
                'subject' => $subject,
                'msg' => $msg,
                'added' => $dt,
            ]);
        }

        $returnto = (string) request()->post('returnto');
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
}
