<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\FriendAddRequest;
use App\Http\Requests\FriendDeleteRequest;
use App\Repositories\FriendsRepository;
use App\Support\Avatar;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
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
    public function __construct(
        private readonly FriendsRepository $friendsRepository,
        private readonly CurrentUser $currentUser,
    ) {}

    public function friends(Request $request): Response|RedirectResponse|View
    {
        $currentUser = (array) ($this->currentUser->get() ?? []);
        $userid = (int) ($request->input('id') ?? $this->currentUser->id());
        if ($userid <= 0 || ! Validators::isId($userid)) {
            return $this->abortResponse(__('friends.std_error'), (__('friends.std_invalid_id')).$userid.'.');
        }

        $action = (string) ($request->input('action') ?? '');

        if ($action === 'add') {
            if (! $request->isMethod('post')) {
                return $this->abortResponse(__('friends.std_error'), ('Permission denied.'));
            }

            return $this->handleAdd($request, $userid);
        }

        if ($action === 'delete') {
            if (! $request->isMethod('post')) {
                return $this->abortResponse(__('friends.std_error'), ('Permission denied.'));
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
                : SafeHtml::fromTrustedHtml(htmlspecialchars($title, ENT_QUOTES, 'UTF-8'));
            $avatar = '';
            if ($this->currentUser->yes('avatars')) {
                $avatar = htmlspecialchars((string) ($friend['avatar'] ?? ''), ENT_QUOTES, 'UTF-8');
            }
            $friend['avatarSrc'] = Avatar::forUser($friendId, $avatar);
            $friend['usernameHtml'] = $userDisplayMap[$friendId] ?? UserDisplay::username($friendId);
            $friend['titleHtml'] = $titleHtml;
            $friend['lastSeen'] = (string) ($friend['last_access'] ?? '');
            $friendsList[] = $friend;
        }

        $blocks = [];
        foreach ($blockRows as $block) {
            $blockId = (int) ($block['id'] ?? 0);
            $blocks[] = [
                'id' => $blockId,
                'usernameHtml' => $userDisplayMap[$blockId] ?? UserDisplay::username($blockId),
            ];
        }

        $titleRow = UserDisplay::row($userid);
        if ($titleRow === false) {
            $titleRow = [];
        }

        return $this->renderPage($request, 'friends', true, [
            'userid' => $userid,
            'friendsList' => $friendsList,
            'blocks' => $blocks,
            'titleUsername' => SafeHtml::fromTrustedHtml($userDisplayMap[$userid] ?? UserDisplay::username($userid)),
            'title' => (__('friends.head_personal_lists_for'))
                .(string) ($titleRow['username'] ?? $currentUser['username'] ?? ''),
            'canViewUserList' => Permission::can(PermissionEnum::VIEW_USER_LIST),
        ]);
    }

    public function friendsPost(Request $request): Response|RedirectResponse|View
    {
        // Old POST /web/friends?action=X callers land on the dedicated
        // endpoints — 308 replays the body unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return match ((string) ($request->input('action') ?? '')) {
            'add' => redirect()->to('/web/friends/add'.$suffix, 308),
            'delete' => redirect()->to('/web/friends/delete'.$suffix, 308),
            default => $this->friends($request),
        };
    }

    public function friendAdd(FriendAddRequest $request): RedirectResponse|Response
    {
        $userid = $this->listOwnerId($request);
        if ($userid <= 0 || ! Validators::isId($userid)) {
            return $this->abortResponse(__('friends.std_error'), (__('friends.std_invalid_id')).$userid.'.');
        }

        return $this->handleAdd($request, $userid);
    }

    public function friendDelete(FriendDeleteRequest $request): RedirectResponse|Response
    {
        $userid = $this->listOwnerId($request);
        if ($userid <= 0 || ! Validators::isId($userid)) {
            return $this->abortResponse(__('friends.std_error'), (__('friends.std_invalid_id')).$userid.'.');
        }

        return $this->handleDelete($request, $userid);
    }

    private function listOwnerId(Request $request): int
    {
        $currentUser = (array) ($this->currentUser->get() ?? []);

        return (int) ($request->input('id') ?? $this->currentUser->id());
    }

    private function handleAdd(Request $request, int $userid): RedirectResponse|Response
    {
        $targetid = $request->input('targetid');
        $type = (string) ($request->input('type') ?? '');

        if (! Validators::isId($targetid)) {
            return $this->abortResponse(__('friends.std_error'), (__('friends.std_invalid_id')).$targetid.'.');
        }
        $targetid = (int) $targetid;

        [$tableIs, $frag, $fieldIs] = $this->resolveType($type);
        if ($tableIs === '') {
            return $this->abortResponse(__('friends.std_error'), (__('friends.std_unknown_type')).$type);
        }

        if ($this->friendsRepository->exists($userid, $type, $targetid)) {
            return $this->abortResponse(__('friends.std_error'), (__('friends.std_user_id')).$targetid.(__('friends.std_already_in')).$tableIs.(__('friends.std_list')));
        }

        $this->friendsRepository->add($userid, $type, $targetid);
        $this->purgeNeighborsCache();

        return redirect('/web/friends?id='.$userid.'#'.$frag);
    }

    private function handleDelete(Request $request, int $userid): RedirectResponse|Response
    {
        $targetid = $request->input('targetid');
        $sure = (int) ($request->input('sure', 0));
        $type = htmlspecialchars((string) ($request->input('type') ?? ''));

        [$tableIs, $frag] = $this->resolveType($type);
        if ($tableIs === '') {
            return $this->abortResponse(__('friends.std_error'), (__('friends.std_unknown_type')).$type);
        }

        $typename = $type === 'friend' ? (__('friends.text_friend')) : (__('friends.text_block'));

        if (! Validators::isId($targetid)) {
            return $this->abortResponse(__('friends.std_error'), (__('friends.std_invalid_id')).$userid.'.');
        }
        $targetid = (int) $targetid;

        if (! $sure) {
            $sureLinkText = (string) __('friends.std_here');
            $sureSuffix = (string) __('friends.std_if_sure');
            $confirm = (__('friends.std_delete_note')).$typename.(__('friends.std_click')).
                view('friends._confirm_delete', [
                    'userid' => $userid,
                    'type' => $type,
                    'targetid' => $targetid,
                    'sureLinkText' => $sureLinkText,
                    'sureSuffix' => $sureSuffix,
                ])->render();

            return $this->abortResponse((__('friends.std_delete')).$type, $confirm, false);
        }

        $deleted = $this->friendsRepository->delete($userid, $type, $targetid);
        if ($deleted === 0) {
            $notFoundKey = $type === 'friend' ? 'std_no_friend_found' : 'std_no_block_found';

            return $this->abortResponse(__('friends.std_error'), __('friends.'.$notFoundKey).$targetid);
        }

        $this->purgeNeighborsCache();

        return redirect('/web/friends?id='.$userid.'#'.$frag);
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
        $cachefile = 'cache/'.Locale::folderFromCookie(Input::cookieValue('c_lang_folder', ''), false).'/neighbors/'.($this->currentUser->id()).'.html';
        if (file_exists($cachefile)) {
            unlink($cachefile);
        }
    }
}
