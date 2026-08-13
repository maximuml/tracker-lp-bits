<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Http\Resources\ForumResource;
use App\Models\Forum;
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

    public function forummanage(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'forummanage');
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
