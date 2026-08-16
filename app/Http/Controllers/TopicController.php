<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Http\Resources\ForumResource;
use App\Http\Resources\TopicResource;
use App\Models\Forum;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Support\Forum as SupportForum;
use App\Support\SupportContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TopicController extends Controller
{
    /**
     * Display a listing of the resource.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function index(Request $request)
    {
        $forumId = $request->forum_id;
        $query = Topic::query()
            ->orderBy("sticky", "desc")
            ->with("user", "firstPost", "lastPost")
        ;
        if ($forumId) {
            $query->where("forumid", $forumId);
        }
        $list = $query->get();
        $resource = TopicResource::collection($list);
        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        SupportContext::setUser($user->toLegacyArray());

        $validated = $request->validate([
            'forumid' => 'required|integer|exists:forums,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $forum = Forum::query()->findOrFail((int) $validated['forumid']);
        if ((int) $user->class < (int) $forum->minclassread || (int) $user->class < (int) $forum->minclasscreate) {
            throw ValidationException::withMessages(['forum' => ['Permission denied.']]);
        }

        $date = now()->toDateTimeString();
        $topicId = ForumRepository::createTopic((int) $user->id, (int) $forum->id, (string) $validated['subject']);
        $postId = ForumRepository::createPost($topicId, (int) $user->id, (string) $validated['body'], $date);

        ForumRepository::updateTopicFirstLastPost($topicId, $postId);
        ForumRepository::incrementForumTopicCount((int) $forum->id);
        ForumRepository::incrementForumPostCount((int) $forum->id);
        ForumRepository::updateUserLastPost((int) $user->id, $date);

        $topic = Topic::query()->findOrFail($topicId);

        return $this->success(new TopicResource($topic->load('user', 'firstPost', 'lastPost')), 'Topic created');
    }

    /**
     * Display the specified resource.
     * @param  \App\Models\Topic  $topic
     * @return  array<string, mixed>
     */
    public function show(Topic $topic)
    {
        $topic->load('user', 'firstPost', 'lastPost');

        return $this->success(new TopicResource($topic));
    }

    /**
     * Update the specified resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Topic  $topic
     * @return  array<string, mixed>
     */
    public function update(Request $request, Topic $topic)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        SupportContext::setUser($user->toLegacyArray());

        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'locked' => 'sometimes|boolean',
            'sticky' => 'sometimes|boolean',
            'hlcolor' => 'sometimes|integer',
        ]);

        $canModerate = SupportForum::isModerator((int) $topic->id, 'topic')
            || Permission::can(PermissionEnum::POST_MANAGE, $user);

        if (! $canModerate && (isset($validated['locked']) || isset($validated['sticky']) || isset($validated['hlcolor']))) {
            throw ValidationException::withMessages(['topic' => ['Permission denied.']]);
        }

        if (isset($validated['subject'])) {
            if (! $canModerate && (int) $topic->userid !== (int) $user->id) {
                throw ValidationException::withMessages(['subject' => ['Permission denied.']]);
            }
            $topic->subject = (string) $validated['subject'];
        }

        if (isset($validated['locked'])) {
            $topic->locked = $validated['locked'] ? 'yes' : 'no';
        }
        if (isset($validated['sticky'])) {
            $topic->sticky = $validated['sticky'] ? 'yes' : 'no';
        }
        if (isset($validated['hlcolor'])) {
            $topic->hlcolor = (int) $validated['hlcolor'];
        }

        $topic->save();

        return $this->success(new TopicResource($topic->fresh()->load('user', 'firstPost', 'lastPost')), 'Topic updated');
    }

    /**
     * Remove the specified resource from storage.
     * @param  \App\Models\Topic  $topic
     * @return  array<string, mixed>
     */
    public function destroy(Topic $topic)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        SupportContext::setUser($user->toLegacyArray());

        if (! SupportForum::isModerator((int) $topic->id, 'topic') && ! Permission::can(PermissionEnum::POST_MANAGE, $user)) {
            throw ValidationException::withMessages(['topic' => ['Permission denied.']]);
        }

        $postCount = ForumRepository::countTopicPosts((int) $topic->id);
        ForumRepository::deleteTopic((int) $topic->id, (int) $topic->forumid, $postCount);

        return $this->success(['success' => true], 'Topic deleted');
    }
}
