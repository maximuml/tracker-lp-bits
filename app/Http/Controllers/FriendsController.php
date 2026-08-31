<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\FriendsRepository;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Input;
use App\Support\Locale;
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

        $userDisplayMap = [];
        foreach (array_filter(array_unique(array_map('intval', $userIds))) as $uid) {
            if ($uid > 0) {
                $userDisplayMap[$uid] = UserDisplay::username($uid);
            }
        }

        $friendsList = [];
        foreach ($friendRows as $friend) {
            $friend['title'] = (string) ($friend['title'] ?? '');
            if ($friend['title'] === '') {
                $friend['title'] = UserClass::name((int) ($friend['class'] ?? 0), false, true, true);
            }
            $friendsList[] = $friend;
        }

        $canViewUserList = Permission::can(PermissionEnum::VIEW_USER_LIST);

        return $this->legacyPageRaw($request, 'friends', true, [
            'userid' => $userid,
            'friendsList' => $friendsList,
            'blockRows' => $blockRows,
            'userDisplayMap' => $userDisplayMap,
            'titleUsername' => $userDisplayMap[$userid] ?? UserDisplay::username($userid),
            'canViewUserList' => $canViewUserList,
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
