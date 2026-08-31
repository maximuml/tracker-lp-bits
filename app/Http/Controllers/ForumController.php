<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Forum\StoreForumDto;
use App\DTOs\Forum\UpdateForumDto;
use App\Http\Resources\ForumResource;
use App\Models\Forum;
use App\Repositories\CommentRepository;
use App\Services\ForumPageService;
use App\Services\ForumService;
use App\Support\CurrentUser;
use App\Support\Pagination;
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

        $data = $this->pageService->build($request);

        return $this->legacyPage($request, 'forum', true, $data);
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

        return $this->legacyPage($request, 'latestcomments', true, [
            'rows' => $rows,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'offset' => $offset,
            'perpage' => $perpage,
            'userDisplayMap' => $userDisplayMap,
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
