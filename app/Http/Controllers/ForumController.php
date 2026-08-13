<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Http\Resources\ForumResource;
use App\Models\Forum;
use App\Support\Forum as SupportForum;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Database\NexusDB;

class ForumController extends LegacyController
{
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

        return view('forum.index');
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
            $topics = NexusDB::table('topics')->where('forumid', $id)->get(['id']);
            foreach ($topics as $topic) {
                NexusDB::table('posts')->where('topicid', $topic->id)->delete();
            }
            NexusDB::table('topics')->where('forumid', $id)->delete();
            NexusDB::table('forums')->where('id', $id)->delete();
            NexusDB::table('forummods')->where('forumid', $id)->delete();
            NexusDB::cache_del('forums_list');
            NexusDB::cache_del('forum_moderator_array');
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
            if ($moderator !== '') {
                SupportForum::setModerators($moderator, $id);
            } else {
                NexusDB::table('forummods')->where('forumid', $id)->delete();
            }
            NexusDB::table('forums')->where('id', $id)->update([
                'sort' => (int) SupportContext::getPost('sort'),
                'name' => $name,
                'description' => $desc,
                'forid' => (int) SupportContext::getPost('overforums'),
                'minclassread' => (int) SupportContext::getPost('readclass'),
                'minclasswrite' => (int) SupportContext::getPost('writeclass'),
                'minclasscreate' => (int) SupportContext::getPost('createclass'),
            ]);
            NexusDB::cache_del('forums_list');
            NexusDB::cache_del('forum_moderator_array');
            return redirect('forummanage.php');
        }

        if ($request->isMethod('post') && SupportContext::getPost('action') === 'addforum') {
            $name = (string) SupportContext::getPost('name');
            $desc = (string) SupportContext::getPost('desc');
            if ($name === '' && $desc === '') {
                return redirect('forummanage.php');
            }
            $id = NexusDB::table('forums')->insertGetId([
                'sort' => (int) SupportContext::getPost('sort'),
                'name' => $name,
                'description' => $desc,
                'minclassread' => (int) SupportContext::getPost('readclass'),
                'minclasswrite' => (int) SupportContext::getPost('writeclass'),
                'minclasscreate' => (int) SupportContext::getPost('createclass'),
                'forid' => (int) SupportContext::getPost('overforums'),
            ]);
            NexusDB::cache_del('forums_list');
            $moderator = (string) SupportContext::getPost('moderator');
            if ($moderator !== '') {
                SupportForum::setModerators($moderator, $id);
            }
            return redirect('forummanage.php');
        }

        $overforums = NexusDB::table('overforums')->orderBy('sort')->get(['id', 'name'])->map(fn ($r) => (array) $r)->all();
        $maxSort = NexusDB::table('forums')->count();

        $classOptions = [];
        $currentClass = UserDisplay::currentClass();
        for ($i = 0; $i <= $currentClass; ++$i) {
            $classOptions[] = ['value' => $i, 'label' => UserClass::name($i, false, true, true)];
        }

        if ($action === 'editforum') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            $row = (array) NexusDB::table('forums')->where('id', $id)->first();
            if (empty($row)) {
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

        $forums = NexusDB::table('forums')
            ->leftJoin('overforums', 'forums.forid', '=', 'overforums.id')
            ->orderBy('forums.sort')
            ->get(['forums.*', 'overforums.name AS of_name'])
            ->map(function ($r) {
                $arr = (array) $r;
                $arr['moderators_html'] = SupportForum::moderatorsWithContext($arr['id'], false);
                return $arr;
            })
            ->all();

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
            NexusDB::table('overforums')->where('id', $id)->delete();
            NexusDB::cache_del('overforums_list');
            return redirect('moforums.php?action=forum');
        }

        if ($request->isMethod('post') && SupportContext::getPost('action') === 'editforum') {
            $postId = (int) (SupportContext::getPost('id') ?? 0);
            $name = (string) SupportContext::getPost('name');
            $desc = (string) SupportContext::getPost('desc');
            if ($postId <= 0 || ($name === '' && $desc === '')) {
                return redirect('moforums.php?action=forum');
            }
            NexusDB::table('overforums')->where('id', $postId)->update([
                'sort' => (int) SupportContext::getPost('sort'),
                'name' => $name,
                'description' => $desc,
                'minclassview' => (int) SupportContext::getPost('viewclass'),
            ]);
            NexusDB::cache_del('overforums_list');
            return redirect('moforums.php?action=forum');
        }

        if ($request->isMethod('post') && SupportContext::getPost('action') === 'addforum') {
            $name = trim((string) SupportContext::getPost('name'));
            $desc = trim((string) SupportContext::getPost('desc'));
            if ($name === '' && $desc === '') {
                return redirect('moforums.php?action=forum');
            }
            NexusDB::table('overforums')->insert([
                'sort' => (int) SupportContext::getPost('sort'),
                'name' => $name,
                'description' => $desc,
                'minclassview' => (int) SupportContext::getPost('viewclass'),
            ]);
            NexusDB::cache_del('overforums_list');
            return redirect('moforums.php?action=forum');
        }

        $maxSort = NexusDB::table('overforums')->count();

        if ($action === 'editforum') {
            $row = (array) NexusDB::table('overforums')->where('id', $id)->first();
            if (empty($row)) {
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

        $overforums = NexusDB::table('overforums')->orderBy('sort')->get()->map(fn ($r) => (array) $r)->all();
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

    public function latestcomments(Request $request): View|RedirectResponse
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
