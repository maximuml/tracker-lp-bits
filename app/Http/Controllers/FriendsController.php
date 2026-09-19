<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\FriendsRepository;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
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
    public function __construct(
        private readonly FriendsRepository $friendsRepository,
        private readonly CurrentUser $currentUser,
    ) {}

    public function friends(Request $request): Response|RedirectResponse|View
    {
        $currentUser = (array) ($this->currentUser->get() ?? []);
        $userid = (int) ($request->input('id') ?? $currentUser['id'] ?? 0);
        if ($userid <= 0 || ! Validators::isId($userid)) {
            return $this->legacyAbortResponse(__('legacy/friends.std_error'), (__('legacy/friends.std_invalid_id')).$userid.'.');
        }

        $action = (string) ($request->input('action') ?? '');

        if ($action === 'add') {
            if (! $request->isMethod('post')) {
                return $this->legacyAbortResponse(__('legacy/friends.std_error'), ('Permission denied.'));
            }

            return $this->handleAdd($request, $userid);
        }

        if ($action === 'delete') {
            if (! $request->isMethod('post')) {
                return $this->legacyAbortResponse(__('legacy/friends.std_error'), ('Permission denied.'));
            }

            return $this->handleDelete($request, $userid);
        }

        $friendRows = $this->friendsRepository->getFriends($userid);
        $blockRows = $this->friendsRepository->getBlocks($userid);

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
            $friend['body1Html'] = SafeHtml::fromTrustedHtml($usernameHtml.' ('.$titleHtml.')<br /><br />'
                .(__('legacy/friends.text_last_seen_on'))
                .(string) Time::format((string) ($friend['last_access'] ?? ''), true, false));
            $friend['body2Html'] = SafeHtml::fromTrustedHtml("<a href=friends.php?id=$userid&action=delete&type=friend&targetid=$friendId>"
                .htmlspecialchars(__('legacy/friends.text_remove_from_friends'), ENT_QUOTES, 'UTF-8').'</a>'
                ."<br /><br /><a href=sendmessage.php?receiver=$friendId>"
                .htmlspecialchars(__('legacy/friends.text_send_pm'), ENT_QUOTES, 'UTF-8').'</a>');
            $friendsList[] = $friend;
        }

        $blocksHtml = __('legacy/friends.text_blocklist_empty');
        if ($blockRows !== []) {
            $blocksHtml = '<table width=100% cellspacing=0 cellpadding=0>';
            foreach (array_values($blockRows) as $i => $block) {
                $blockId = (int) ($block['id'] ?? 0);
                if ($i % 6 === 0) {
                    $blocksHtml .= '<tr>';
                }
                $blocksHtml .= "<td>[<span class='small'><a href=friends.php?id=$userid&action=delete&type=block&targetid=$blockId>D</a></span>] "
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
            'blocksHtml' => SafeHtml::fromTrustedHtml($blocksHtml),
            'titleUsername' => SafeHtml::fromTrustedHtml($userDisplayMap[$userid] ?? UserDisplay::username($userid)),
            'title' => (__('legacy/friends.head_personal_lists_for'))
                .(string) ($titleRow['username'] ?? $currentUser['username'] ?? ''),
            'canViewUserList' => Permission::can(PermissionEnum::VIEW_USER_LIST),
        ]);
    }

    private function handleAdd(Request $request, int $userid): RedirectResponse|Response
    {
        $targetid = $request->input('targetid');
        $type = (string) ($request->input('type') ?? '');

        if (! Validators::isId($targetid)) {
            return $this->legacyAbortResponse(__('legacy/friends.std_error'), (__('legacy/friends.std_invalid_id')).$targetid.'.');
        }
        $targetid = (int) $targetid;

        [$tableIs, $frag, $fieldIs] = $this->resolveType($type);
        if ($tableIs === '') {
            return $this->legacyAbortResponse(__('legacy/friends.std_error'), (__('legacy/friends.std_unknown_type')).$type);
        }

        if ($this->friendsRepository->exists($userid, $type, $targetid)) {
            return $this->legacyAbortResponse(__('legacy/friends.std_error'), (__('legacy/friends.std_user_id')).$targetid.(__('legacy/friends.std_already_in')).$tableIs.(__('legacy/friends.std_list')));
        }

        $this->friendsRepository->add($userid, $type, $targetid);
        $this->purgeNeighborsCache();

        return redirect('/friends.php?id='.$userid.'#'.$frag);
    }

    private function handleDelete(Request $request, int $userid): RedirectResponse|Response
    {
        $targetid = $request->input('targetid');
        $sure = (int) ($request->input('sure', 0));
        $type = htmlspecialchars((string) ($request->input('type') ?? ''));

        [$tableIs, $frag] = $this->resolveType($type);
        if ($tableIs === '') {
            return $this->legacyAbortResponse(__('legacy/friends.std_error'), (__('legacy/friends.std_unknown_type')).$type);
        }

        $typename = $type === 'friend' ? (__('legacy/friends.text_friend')) : (__('legacy/friends.text_block'));

        if (! Validators::isId($targetid)) {
            return $this->legacyAbortResponse(__('legacy/friends.std_error'), (__('legacy/friends.std_invalid_id')).$userid.'.');
        }
        $targetid = (int) $targetid;

        if (! $sure) {
            $confirm = (__('legacy/friends.std_delete_note')).$typename.(__('legacy/friends.std_click')).
                "<a href=\"?id=$userid&action=delete&type=$type&targetid=$targetid&sure=1\">".(__('legacy/friends.std_here_if_sure')).'</a>';

            return $this->legacyAbortResponse((__('legacy/friends.std_delete')).$type, $confirm, false);
        }

        $deleted = $this->friendsRepository->delete($userid, $type, $targetid);
        if ($deleted === 0) {
            $notFoundKey = $type === 'friend' ? 'std_no_friend_found' : 'std_no_block_found';

            return $this->legacyAbortResponse(__('legacy/friends.std_error'), __('legacy/friends.'.$notFoundKey).$targetid);
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
        $currentUser = (array) ($this->currentUser->get() ?? []);
        $cachefile = 'cache/'.Locale::folderFromCookie(Input::cookieValue('c_lang_folder', ''), false).'/neighbors/'.($currentUser['id'] ?? 0).'.html';
        if (file_exists($cachefile)) {
            unlink($cachefile);
        }
    }
}
