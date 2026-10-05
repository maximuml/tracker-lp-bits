<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\ForumRepositoryInterface;
use App\DTOs\Forum\StoreForumDto;
use App\DTOs\Forum\UpdateForumDto;
use App\Http\Requests\ForumDeletePostRequest;
use App\Http\Requests\ForumDeleteTopicRequest;
use App\Http\Requests\ForumMoveTopicRequest;
use App\Http\Requests\ForumPostRequest;
use App\Http\Requests\ForumTopicActionRequest;
use App\Http\Resources\ForumResource;
use App\Models\Forum;
use App\Repositories\CommentRepository;
use App\Services\ForumPageService;
use App\Services\ForumService;
use App\Support\Avatar;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\LegacyYesNo;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ForumController extends LegacyController
{
    public function __construct(private readonly ForumRepositoryInterface $forumRepository,
        private readonly ForumService $service,
        private readonly ForumPageService $pageService,
        private readonly CurrentUser $currentUser,
        private readonly CommentRepository $commentRepository,
    ) {}

    /**
     * Serve the legacy forums.php page from a Laravel view.
     *
     * Mutation actions (post, movetopic, deletetopic, deletepost,
     * setlocked, setsticky, hltopic) are handled by ForumService and
     * produce redirects. Read-only actions are rendered via
     * ForumPageService + Blade partials under resources/views/forum/.
     */
    public function legacy(Request $request): View|Response|RedirectResponse
    {
        if ($this->currentUser->get() === null) {
            return redirect('/forums?'.$request->getQueryString());
        }

        $result = $this->service->legacy($request);
        if (! is_array($result)) {
            return $result;
        }

        $data = $this->pageService->build($request)->toArray();

        return $this->legacyPage($request, 'forum', true, $data);
    }

    public function legacyAction(Request $request): Response|RedirectResponse
    {
        if ($this->currentUser->get() === null) {
            return redirect('/forums?'.$request->getQueryString());
        }

        $action = (string) $request->input('action', '');
        // Forms can carry params in the URL (?action=hltopic&topicid=N) —
        // forward the query string so the target endpoint still sees them.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        $target = match ($action) {
            'post' => '/web/forums/post',
            'movetopic' => '/web/forums/movetopic',
            'deletetopic' => '/web/forums/deletetopic',
            'deletepost' => '/web/forums/deletepost',
            'setlocked' => '/web/forums/setlocked',
            'hltopic' => '/web/forums/hltopic',
            'setsticky' => '/web/forums/setsticky',
            default => null,
        };
        if ($target !== null) {
            return redirect()->to($target.$suffix, 308);
        }

        $result = $this->service->legacy($request);
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return redirect('/forums');
    }

    public function post(ForumPostRequest $request): Response|RedirectResponse
    {
        return $this->forumsGate($request) ?? $this->service->post($request);
    }

    public function moveTopic(ForumMoveTopicRequest $request): Response|RedirectResponse
    {
        return $this->forumsGate($request) ?? $this->service->moveTopic($request);
    }

    public function deleteTopic(ForumDeleteTopicRequest $request): Response|RedirectResponse
    {
        return $this->forumsGate($request) ?? $this->service->deleteTopic($request);
    }

    public function deletePost(ForumDeletePostRequest $request): Response|RedirectResponse
    {
        return $this->forumsGate($request) ?? $this->service->deletePost($request);
    }

    public function setLocked(ForumTopicActionRequest $request): Response|RedirectResponse
    {
        return $this->forumsGate($request) ?? $this->service->setLocked($request);
    }

    public function highlightTopic(ForumTopicActionRequest $request): Response|RedirectResponse
    {
        return $this->forumsGate($request) ?? $this->service->highlightTopic($request);
    }

    public function setSticky(ForumTopicActionRequest $request): Response|RedirectResponse
    {
        return $this->forumsGate($request) ?? $this->service->setSticky($request);
    }

    private function forumsGate(Request $request): ?RedirectResponse
    {
        if ($this->currentUser->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/forums'.($qs !== null && $qs !== '' ? '?'.$qs : ''));
        }

        return null;
    }

    public function latestcomments(Request $request): View|RedirectResponse|Response
    {
        $perpage = 20;
        $count = $this->commentRepository->countLatest();

        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $count, '/web/latestcomments?');
        $rows = $this->commentRepository->getLatest($perpage, $offset);

        $userIds = array_filter(array_unique(array_column($rows, 'user')));
        UserDisplay::preload(array_map('intval', $userIds));
        $userDisplayMap = [];
        foreach ($userIds as $uid) {
            $userDisplayMap[(int) $uid] = UserDisplay::username((int) $uid, false, true, true, false, false, true);
        }

        $showAvatars = LegacyYesNo::isYes(((array) ($this->currentUser->get() ?? []))['avatars'] ?? null);
        foreach ($rows as &$row) {
            $row = (array) $row;
            $commentId = (int) ($row['id'] ?? 0);
            $parentType = (string) ($row['parent_type'] ?? '');
            $parentId = (int) ($row['parent_id'] ?? 0);
            $parentUrl = '';
            if ($parentType === 'torrent' && $parentId > 0) {
                $parentUrl = "/web/details/{$parentId}?hit=1#cid{$commentId}";
            } elseif ($parentType === 'offer' && $parentId > 0) {
                $parentUrl = "/web/offers?id={$parentId}&off_details=1#cid{$commentId}";
            }
            $row['parentUrl'] = $parentUrl;
            $avatar = $showAvatars ? htmlspecialchars(trim((string) ($row['avatar'] ?? ''))) : '';
            $row['avatarHtml'] = SafeHtml::fromTrustedHtml(UserDisplay::avatarImageWithContext(Avatar::forUser((int) ($row['user'] ?? 0), $avatar)));
            $row['usernameHtml'] = SafeHtml::fromTrustedHtml($userDisplayMap[(int) ($row['user'] ?? 0)]
                ?? UserDisplay::username((int) ($row['user'] ?? 0), false, true, true, false, false, true));
            $row['timeHtml'] = SafeHtml::fromTrustedHtml((string) Time::format((string) ($row['added'] ?? '')));
            $row['commentHtml'] = Format::formatComment((string) ($row['text'] ?? ''));
        }
        unset($row);

        return $this->legacyPage($request, 'latestcomments', true, [
            'rows' => $rows,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'offset' => $offset,
            'perpage' => $perpage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $forums = $this->forumRepository->listOrdered();

        return $this->success(ForumResource::collection($forums));
    }

    /**
     * @return array<string, mixed>
     */
    public function store(Request $request): array
    {
        $forum = $this->forumRepository->create(StoreForumDto::fromRequest($request)->toArray());

        return $this->success(new ForumResource($forum), 'Forum created');
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Forum $forum): array
    {
        return $this->success(new ForumResource($forum));
    }

    /**
     * @return array<string, mixed>
     */
    public function update(Request $request, Forum $forum): array
    {
        $forum->update(UpdateForumDto::fromRequest($request)->toArray());

        return $this->success(new ForumResource($forum->fresh()), 'Forum updated');
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Forum $forum): array
    {
        $forum->delete();

        return $this->success(['success' => true], 'Forum deleted');
    }
}
