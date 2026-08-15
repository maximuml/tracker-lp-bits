<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Http\Resources\ForumResource;
use App\Models\Forum;
use App\Services\Legacy\LegacyPartialRenderer;
use App\Support\Forum as SupportForum;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Repositories\ForumRepository;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ForumController extends LegacyController
{
    private LegacyPartialRenderer $renderer;

    public function __construct(LegacyPartialRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Serve the legacy forums.php page from a Laravel view.
     *
     * The real markup lives in resources/views/forum/_forums_legacy.php
     * and is included by forum/index.blade.php so the original HTML/PHP
     * interleaving is preserved as closely as possible.
     */
    public function legacy(Request $request): View|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            return redirect('/forums.php?' . $request->getQueryString());
        }

        $result = $this->renderer->render('forum_forums');
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return $this->legacyPage($request, 'forum', true, $result);
    }

    public function forummanage(Request $request): View|RedirectResponse|Response
    {
        if (! Permissions::userCan(PermissionEnum::FORUM_MANAGE->value, false, (int) (SupportContext::getUser()['id'] ?? 0))) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $action = (string) (SupportContext::getQuery('action') ?? '');
        $currentUser = SupportContext::getUser() ?? [];

        if ($action === 'del') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            if ($id <= 0) {
                return redirect('forummanage.php');
            }
            app(ForumRepository::class)->deleteForum($id);
            return redirect('forummanage.php');
        }

        if ($request->isMethod('post') && SupportContext::getPost('action') === 'editforum') {
            $id = (int) (SupportContext::getPost('id') ?? 0);
            $name = (string) SupportContext::getPost('name');
            $desc = (string) SupportContext::getPost('desc');
            if ($id <= 0 || ($name === '' && $desc === '')) {
                return redirect('forummanage.php');
            }
            $moderator = (string) SupportContext::getPost('moderator');
            $data = [
                'sort' => (int) SupportContext::getPost('sort'),
                'name' => $name,
                'description' => $desc,
                'forid' => (int) SupportContext::getPost('overforums'),
                'minclassread' => (int) SupportContext::getPost('readclass'),
                'minclasswrite' => (int) SupportContext::getPost('writeclass'),
                'minclasscreate' => (int) SupportContext::getPost('createclass'),
            ];
            app(ForumRepository::class)->updateForum($id, $data);
            if ($moderator !== '') {
                SupportForum::setModerators($moderator, $id);
            } else {
                app(ForumRepository::class)->replaceModerators($id, []);
            }
            return redirect('forummanage.php');
        }

        if ($request->isMethod('post') && SupportContext::getPost('action') === 'addforum') {
            $name = (string) SupportContext::getPost('name');
            $desc = (string) SupportContext::getPost('desc');
            if ($name === '' && $desc === '') {
                return redirect('forummanage.php');
            }
            $data = [
                'sort' => (int) SupportContext::getPost('sort'),
                'name' => $name,
                'description' => $desc,
                'minclassread' => (int) SupportContext::getPost('readclass'),
                'minclasswrite' => (int) SupportContext::getPost('writeclass'),
                'minclasscreate' => (int) SupportContext::getPost('createclass'),
                'forid' => (int) SupportContext::getPost('overforums'),
            ];
            $id = app(ForumRepository::class)->createForum($data);
            $moderator = (string) SupportContext::getPost('moderator');
            if ($moderator !== '') {
                SupportForum::setModerators($moderator, $id);
            }
            return redirect('forummanage.php');
        }

        $overforums = app(ForumRepository::class)->getOverforums();
        $maxSort = app(ForumRepository::class)->getMaxForumSort();

        $classOptions = [];
        $currentClass = UserDisplay::currentClass();
        for ($i = 0; $i <= $currentClass; ++$i) {
            $classOptions[] = ['value' => $i, 'label' => UserClass::name($i, false, true, true)];
        }

        if ($action === 'editforum') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            $row = app(ForumRepository::class)->getForumRow($id);
            if ($row === null) {
                return $this->legacyAbortResponse('Error', 'No records found.');
            }

            $moderatorUsernames = SupportForum::moderatorsWithContext($row['id'], true);

            return $this->legacyPage($request, 'forummanage', true, [
                'mode' => 'editforum',
                'id' => $id,
                'row' => $row,
                'overforums' => $overforums,
                'maxSort' => $maxSort,
                'classOptions' => $classOptions,
                'moderatorUsernames' => $moderatorUsernames,
                'lang_forummanage' => (array) SupportContext::getGlobal('lang_forummanage', []),
            ]);
        }

        if ($action === 'newforum') {
            return $this->legacyPage($request, 'forummanage', true, [
                'mode' => 'newforum',
                'overforums' => $overforums,
                'maxSort' => $maxSort,
                'classOptions' => $classOptions,
                'currentClass' => (int) ($currentUser['class'] ?? 0),
                'lang_forummanage' => (array) SupportContext::getGlobal('lang_forummanage', []),
            ]);
        }

        $forums = app(ForumRepository::class)->getForumsWithOverforum();
        foreach ($forums as &$arr) {
            $arr['moderators_html'] = SupportForum::moderatorsWithContext($arr['id'], false);
        }
        unset($arr);

        return $this->legacyPage($request, 'forummanage', true, [
            'mode' => 'list',
            'forums' => $forums,
            'lang_forummanage' => (array) SupportContext::getGlobal('lang_forummanage', []),
        ]);
    }

    public function moforums(Request $request): View|RedirectResponse|Response
    {
        if (! Permissions::userCan(PermissionEnum::FORUM_MANAGE->value, false, (int) (SupportContext::getUser()['id'] ?? 0))) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $action = (string) (SupportContext::getQuery('action') ?? 'forum');
        $id = (int) (SupportContext::getQuery('id') ?? 0);
        $langMoforums = (array) SupportContext::getGlobal('lang_moforums', []);
        $currentUser = SupportContext::getUser() ?? [];

        if ($action === 'del') {
            if ($id <= 0) {
                return redirect('moforums.php?action=forum');
            }
            app(ForumRepository::class)->deleteOverforum($id);
            return redirect('moforums.php?action=forum');
        }

        if ($request->isMethod('post') && SupportContext::getPost('action') === 'editforum') {
            $postId = (int) (SupportContext::getPost('id') ?? 0);
            $name = (string) SupportContext::getPost('name');
            $desc = (string) SupportContext::getPost('desc');
            if ($postId <= 0 || ($name === '' && $desc === '')) {
                return redirect('moforums.php?action=forum');
            }
            $data = [
                'sort' => (int) SupportContext::getPost('sort'),
                'name' => $name,
                'description' => $desc,
                'minclassview' => (int) SupportContext::getPost('viewclass'),
            ];
            app(ForumRepository::class)->updateOverforum($postId, $data);
            return redirect('moforums.php?action=forum');
        }

        if ($request->isMethod('post') && SupportContext::getPost('action') === 'addforum') {
            $name = trim((string) SupportContext::getPost('name'));
            $desc = trim((string) SupportContext::getPost('desc'));
            if ($name === '' && $desc === '') {
                return redirect('moforums.php?action=forum');
            }
            $data = [
                'sort' => (int) SupportContext::getPost('sort'),
                'name' => $name,
                'description' => $desc,
                'minclassview' => (int) SupportContext::getPost('viewclass'),
            ];
            app(ForumRepository::class)->createOverforum($data);
            return redirect('moforums.php?action=forum');
        }

        $maxSort = app(ForumRepository::class)->getMaxOverforumSort();

        if ($action === 'editforum') {
            $row = app(ForumRepository::class)->getOverforumRow($id);
            if ($row === null) {
                return $this->legacyAbortResponse('Error', 'No records found.');
            }
            return $this->legacyPage($request, 'moforums', true, [
                'mode' => 'editforum',
                'id' => $id,
                'row' => $row,
                'maxSort' => $maxSort,
                'lang_moforums' => $langMoforums,
            ]);
        }

        $overforums = app(ForumRepository::class)->getAllOverforums();
        $viewclassOptions = [];
        $currentClass = UserDisplay::currentClass();
        for ($i = 0; $i <= $currentClass; ++$i) {
            $viewclassOptions[] = ['value' => $i, 'label' => UserClass::name($i, false, true, true)];
        }
        $sortOptions = [];
        for ($i = 0; $i <= $maxSort + 1; ++$i) {
            $sortOptions[] = ['value' => $i, 'label' => (string) $i];
        }

        return $this->legacyPage($request, 'moforums', true, [
            'mode' => 'forum',
            'overforums' => $overforums,
            'currentClass' => (int) ($currentUser['class'] ?? 0),
            'maxSort' => $maxSort,
            'viewclassOptions' => $viewclassOptions,
            'sortOptions' => $sortOptions,
            'lang_moforums' => $langMoforums,
        ]);

    }

    public function latestcomments(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'latestcomments', true);
    }

    /**
     * @return  array<string, mixed>
     */
    public function index(): array
    {
        $forums = Forum::query()->orderBy('sort')->get();

        return $this->success(ForumResource::collection($forums));
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function store(Request $request): array
    {
        $forum = Forum::query()->create($request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'forid' => 'required|integer',
            'minclassread' => 'required|integer',
            'minclasswrite' => 'required|integer',
            'minclasscreate' => 'required|integer',
        ]));

        return $this->success(new ForumResource($forum), 'Forum created');
    }

    /**
     * @param  \App\Models\Forum  $forum
     * @return  array<string, mixed>
     */
    public function show(Forum $forum): array
    {
        return $this->success(new ForumResource($forum));
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Forum  $forum
     * @return  array<string, mixed>
     */
    public function update(Request $request, Forum $forum): array
    {
        $forum->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'forid' => 'sometimes|integer',
            'minclassread' => 'sometimes|integer',
            'minclasswrite' => 'sometimes|integer',
            'minclasscreate' => 'sometimes|integer',
        ]));

        return $this->success(new ForumResource($forum->fresh()), 'Forum updated');
    }

    /**
     * @param  \App\Models\Forum  $forum
     * @return  array<string, mixed>
     */
    public function destroy(Forum $forum): array
    {
        $forum->delete();

        return $this->success(['success' => true], 'Forum deleted');
    }
}
