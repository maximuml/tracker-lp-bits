<?php

namespace App\Http\Controllers;

use App\Http\Resources\ForumResource;
use App\Models\Forum;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

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
        if (! defined('IN_NEXUS') || ! IN_NEXUS || SupportContext::getUser() === null) {
            return redirect('/forums.php?' . $request->getQueryString());
        }

        return view('forum.index');
    }

    public function forummanage(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'forummanage');
    }

    public function moforums(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'moforums');
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
