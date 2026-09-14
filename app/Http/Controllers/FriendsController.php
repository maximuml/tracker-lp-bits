<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\FriendsRepository;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Input;
use App\Support\LegacyYesNo;
use App\Support\Locale;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class FriendsController extends LegacyController
{
    public function friends(Request $request): Response|RedirectResponse|View
    {
        $currentUser = (array) (app(CurrentUser::class)->get() ?? []);
        $langFriends = (array) (app(Globals::class)->get('lang_friends') ?? []);

        $userid = (int) ($request->input('id') ?? $currentUser['id'] ?? 0);
        if ($userid <= 0 || ! Validators::isId($userid)) {
            return $this->legacyAbortResponse($langFriends['std_error'] ?? 'Error', ($langFriends['std_invalid_id'] ?? 'Invalid ID ').$userid.'.');
        }

        $action = (string) ($request->input('action') ?? '');

        if ($action === 'add') {
            if (! $request->isMethod('post')) {
                return $this->legacyAbortResponse($langFriends['std_error'] ?? 'Error', $langFriends['std_permission_denied'] ?? 'Permission denied.');
            }

            return $this->handleAdd($request, $userid, $langFriends);
        }

        if ($action === 'delete') {
            if (! $request->isMethod('post')) {
                return $this->legacyAbortResponse($langFriends['std_error'] ?? 'Error', $langFriends['std_permission_denied'] ?? 'Permission denied.');
            }

            return $this->handleDelete($request, $userid, $langFriends);
        }

        $friendRows = app(FriendsRepository::class)->getFriends($userid);
        $blockRows = app(FriendsRepository::class)->getBlocks($userid);

        $userIds = array_merge(
            array_column($friendRows, 'id'),
            array_column($blockRows, 'id'),
            [$userid],
        );

        $validUserIds = array_filter(array_unique(array_map('intval', $userIds)), fn ($uid) => $uid > 0);
        UserDisplay::preload(array_values($validUserIds));
        $userDisplayMap = [];
        foreach ($validUserIds as $uid) {
            $userDisplayMap[$uid] = UserDisplay::username($uid);
        }

        $friendsList = [];
        foreach ($friendRows as $friend) {
            $friendId = (int) ($friend['id'] ?? 0);
            $title = (string) ($friend['title'] ?? '');
            $titleHtml = $title === ''
                ? UserClass::name((int) ($friend['class'] ?? 0), false, true, true)
                : htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $avatar = '';
            if (LegacyYesNo::isYes($currentUser['avatars'] ?? null)) {
                $avatar = htmlspecialchars((string) ($friend['avatar'] ?? ''), ENT_QUOTES, 'UTF-8');
            }
            if ($avatar === '') {
                $avatar = 'pic/default_avatar.png';
            }
            $usernameHtml = $userDisplayMap[$friendId] ?? UserDisplay::username($friendId);
            $friend['avatarSrc'] = $avatar;
            $friend['body1Html'] = $usernameHtml.' ('.$titleHtml.')<br /><br />'
                .($langFriends['text_last_seen_on'] ?? 'Last seen on ')
                .(string) Time::format((string) ($friend['last_access'] ?? ''), true, false);
            $friend['body2Html'] = "<a href=friends.php?id=$userid&action=delete&type=friend&targetid=$friendId>"
                .htmlspecialchars($langFriends['text_remove_from_friends'] ?? 'Remove from friends', ENT_QUOTES, 'UTF-8').'</a>'
                ."<br /><br /><a href=sendmessage.php?receiver=$friendId>"
                .htmlspecialchars($langFriends['text_send_pm'] ?? 'Send PM', ENT_QUOTES, 'UTF-8').'</a>';
            $friendsList[] = $friend;
        }

        $blocksHtml = $langFriends['text_blocklist_empty'] ?? 'No blocked users.';
        if ($blockRows !== []) {
            $blocksHtml = '<table width=100% cellspacing=0 cellpadding=0>';
            foreach (array_values($blockRows) as $i => $block) {
                $blockId = (int) ($block['id'] ?? 0);
                if ($i % 6 === 0) {
                    $blocksHtml .= '<tr>';
                }
                $blocksHtml .= "<td style='border: none; padding: 4px; spacing: 0px;'>[<font class=small><a href=friends.php?id=$userid&action=delete&type=block&targetid=$blockId>D</a></font>] "
                    .($userDisplayMap[$blockId] ?? UserDisplay::username($blockId)).'</td>';
                if ($i % 6 === 5) {
                    $blocksHtml .= '</tr>';
                }
            }
            $blocksHtml .= "</table>\n";
        }

        $titleRow = UserDisplay::row($userid);
        if ($titleRow === false) {
            $titleRow = [];
        }

        return $this->legacyPageRaw($request, 'friends', true, [
            'userid' => $userid,
            'friendsList' => $friendsList,
            'blocksHtml' => $blocksHtml,
            'titleUsername' => $userDisplayMap[$userid] ?? UserDisplay::username($userid),
            'title' => ($langFriends['head_personal_lists_for'] ?? 'Personal lists for ')
                .(string) ($titleRow['username'] ?? $currentUser['username'] ?? ''),
            'canViewUserList' => Permission::can(PermissionEnum::VIEW_USER_LIST),
        ]);
    }

    /**
     * @param  array<string, mixed>  $langFriends
     */
    private function handleAdd(Request $request, int $userid, array $langFriends): RedirectResponse|Response
    {
        $targetid = $request->input('targetid');
        $type = (string) ($request->input('type') ?? '');

        if (! Validators::isId($targetid)) {
            return $this->legacyAbortResponse($langFriends['std_error'] ?? 'Error', ($langFriends['std_invalid_id'] ?? 'Invalid ID ').$targetid.'.');
        }
        $targetid = (int) $targetid;

        [$tableIs, $frag, $fieldIs] = $this->resolveType($type);
        if ($tableIs === '') {
            return $this->legacyAbortResponse($langFriends['std_error'] ?? 'Error', ($langFriends['std_unknown_type'] ?? 'Unknown type ').$type);
        }

        if (app(FriendsRepository::class)->exists($userid, $type, $targetid)) {
            return $this->legacyAbortResponse($langFriends['std_error'] ?? 'Error', ($langFriends['std_user_id'] ?? 'User ').$targetid.($langFriends['std_already_in'] ?? ' is already in ').$tableIs.($langFriends['std_list'] ?? ' list.'));
        }

        app(FriendsRepository::class)->add($userid, $type, $targetid);
        $this->purgeNeighborsCache();

        return redirect('/friends.php?id='.$userid.'#'.$frag);
    }

    /**
     * @param  array<string, mixed>  $langFriends
     */
    private function handleDelete(Request $request, int $userid, array $langFriends): RedirectResponse|Response
    {
        $targetid = $request->input('targetid');
        $sure = (int) ($request->input('sure', 0));
        $type = htmlspecialchars((string) ($request->input('type') ?? ''));

        [$tableIs, $frag] = $this->resolveType($type);
        if ($tableIs === '') {
            return $this->legacyAbortResponse($langFriends['std_error'] ?? 'Error', ($langFriends['std_unknown_type'] ?? 'Unknown type ').$type);
        }

        $typename = $type === 'friend' ? ($langFriends['text_friend'] ?? 'friend') : ($langFriends['text_block'] ?? 'block');

        if (! Validators::isId($targetid)) {
            return $this->legacyAbortResponse($langFriends['std_error'] ?? 'Error', ($langFriends['std_invalid_id'] ?? 'Invalid ID ').$userid.'.');
        }
        $targetid = (int) $targetid;

        if (! $sure) {
            $confirm = ($langFriends['std_delete_note'] ?? 'Delete note ').$typename.($langFriends['std_click'] ?? ' click ').
                "<a href=\"?id=$userid&action=delete&type=$type&targetid=$targetid&sure=1\">".($langFriends['std_here_if_sure'] ?? 'here if sure').'</a>';

            return $this->legacyAbortResponse(($langFriends['std_delete'] ?? 'Delete ').$type, $confirm, false);
        }

        $deleted = app(FriendsRepository::class)->delete($userid, $type, $targetid);
        if ($deleted === 0) {
            $notFoundKey = $type === 'friend' ? 'std_no_friend_found' : 'std_no_block_found';

            return $this->legacyAbortResponse($langFriends['std_error'] ?? 'Error', ($langFriends[$notFoundKey] ?? 'Not found ').$targetid);
        }

        $this->purgeNeighborsCache();

        return redirect('/friends.php?id='.$userid.'#'.$frag);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveType(string $type): array
    {
        return match ($type) {
            'friend' => ['friends', 'friends', 'friendid'],
            'block' => ['blocks', 'blocks', 'blockid'],
            default => ['', '', ''],
        };
    }

    private function purgeNeighborsCache(): void
    {
        $currentUser = (array) (app(CurrentUser::class)->get() ?? []);
        $cachefile = 'cache/'.Locale::folderFromCookie(Input::cookieValue('c_lang_folder', ''), false).'/neighbors/'.($currentUser['id'] ?? 0).'.html';
        if (file_exists($cachefile)) {
            unlink($cachefile);
        }
    }
}
