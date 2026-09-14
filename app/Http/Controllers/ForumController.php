<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Forum\StoreForumDto;
use App\DTOs\Forum\UpdateForumDto;
use App\Http\Requests\ForumMoveTopicRequest;
use App\Http\Requests\ForumPostRequest;
use App\Http\Requests\ForumTopicActionRequest;
use App\Http\Resources\ForumResource;
use App\Models\Forum;
use App\Repositories\CommentRepository;
use App\Services\ForumPageService;
use App\Services\ForumService;
use App\Support\CurrentUser;
use App\Support\Format;
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
    public function __construct(
        private readonly ForumService $service,
        private readonly ForumPageService $pageService,
        private readonly CurrentUser $currentUser,
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
        if (app(CurrentUser::class)->get() === null) {
            return redirect('/forums.php?'.$request->getQueryString());
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
        if (app(CurrentUser::class)->get() === null) {
            return redirect('/forums.php?'.$request->getQueryString());
        }

        // W1-04: Validate POST mutations by action type before delegating
        $action = (string) $request->input('action', '');
        $rules = match ($action) {
            'post' => (new ForumPostRequest)->rules(),
            'movetopic' => (new ForumMoveTopicRequest)->rules(),
            'setlocked', 'setsticky', 'hltopic' => (new ForumTopicActionRequest)->rules(),
            default => [],
        };

        if ($rules !== []) {
            // W2-02: validate only the subset of request fields that have rules.
            // This keeps the dynamic rule selection while avoiding $request->all().
            $validator = validator($request->only(array_keys($rules)), $rules);
            if ($validator->fails()) {
                return redirect('/forums');
            }
        }

        $result = $this->service->legacy($request);
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return redirect('/forums');
    }

    public function latestcomments(Request $request): View|RedirectResponse|Response
    {
        $perpage = 20;
        $count = app(CommentRepository::class)->countLatest();

        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $count, 'latestcomments.php?');
        $rows = app(CommentRepository::class)->getLatest($perpage, $offset);

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
                $parentUrl = "details.php?id={$parentId}&hit=1#cid{$commentId}";
            } elseif ($parentType === 'offer' && $parentId > 0) {
                $parentUrl = "offers.php?id={$parentId}&off_details=1#cid{$commentId}";
            }
            $row['parentLinkHtml'] = $parentUrl !== ''
                ? ' <font color="gray">on</font> <a href="'.$parentUrl.'">'.htmlspecialchars((string) ($row['parent_name'] ?? '')).'</a>'
                : '';
            $avatar = $showAvatars ? htmlspecialchars(trim((string) ($row['avatar'] ?? ''))) : '';
            $row['avatarHtml'] = UserDisplay::avatarImageWithContext($avatar !== '' ? $avatar : 'pic/default_avatar.png');
            $row['usernameHtml'] = $userDisplayMap[(int) ($row['user'] ?? 0)]
                ?? UserDisplay::username((int) ($row['user'] ?? 0), false, true, true, false, false, true);
            $row['timeHtml'] = (string) Time::format((string) ($row['added'] ?? ''));
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
        $forums = Forum::query()->orderBy('sort')->get();

        return $this->success(ForumResource::collection($forums));
    }

    /**
     * @return array<string, mixed>
     */
    public function store(Request $request): array
    {
        $forum = Forum::query()->create(StoreForumDto::fromRequest($request)->toArray());

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
