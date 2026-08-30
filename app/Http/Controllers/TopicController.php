<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\DTOs\Forum\ListTopicsDto;
use App\DTOs\Forum\StoreTopicDto;
use App\DTOs\Forum\UpdateTopicDto;
use App\Enums\Permission\PermissionEnum;
use App\Http\Resources\TopicResource;
use App\Models\Forum;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Support\CurrentUser;
use App\Support\Forum as SupportForum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TopicController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $dto = ListTopicsDto::fromRequest($request);
        $query = Topic::query()
            ->orderBy('sticky', 'desc')
            ->with('user', 'firstPost', 'lastPost');
        if ($dto->forumId !== null) {
            $query->where('forumid', $dto->forumId);
        }
        $list = $query->get();
        $resource = TopicResource::collection($list);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(Request $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        app(CurrentUser::class)->set($user->toLegacyArray());

        $dto = StoreTopicDto::fromRequest($request);

        $forum = Forum::query()->findOrFail($dto->forumId);
        if ((int) $user->class < (int) $forum->minclassread || (int) $user->class < (int) $forum->minclasscreate) {
            throw ValidationException::withMessages(['forum' => ['Permission denied.']]);
        }

        $date = now()->toDateTimeString();
        $topicId = ForumRepository::createTopic((int) $user->id, (int) $forum->id, $dto->subject);
        $postId = ForumRepository::createPost($topicId, (int) $user->id, $dto->body, $date);

        ForumRepository::updateTopicFirstLastPost($topicId, $postId);
        ForumRepository::incrementForumTopicCount((int) $forum->id);
        ForumRepository::incrementForumPostCount((int) $forum->id);
        ForumRepository::updateUserLastPost((int) $user->id, $date);

        $topic = Topic::query()->findOrFail($topicId);

        return $this->success(new TopicResource($topic->load('user', 'firstPost', 'lastPost')), 'Topic created');
    }

    /**
     * Display the specified resource.
     *
     * @return array<string, mixed>
     */
    public function show(Topic $topic): array
    {
        $topic->load('user', 'firstPost', 'lastPost');

        return $this->success(new TopicResource($topic));
    }

    /**
     * Update the specified resource in storage.
     *
     * @return array<string, mixed>
     */
    public function update(Request $request, Topic $topic): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        app(CurrentUser::class)->set($user->toLegacyArray());

        $dto = UpdateTopicDto::fromRequest($request);

        $canModerate = SupportForum::isModerator((int) $topic->id, 'topic')
            || Permission::can(PermissionEnum::POST_MANAGE, $user);

        if (! $canModerate && ($dto->locked !== null || $dto->sticky !== null || $dto->hlcolor !== null)) {
            throw ValidationException::withMessages(['topic' => ['Permission denied.']]);
        }

        if ($dto->subject !== null) {
            if (! $canModerate && (int) $topic->userid !== (int) $user->id) {
                throw ValidationException::withMessages(['subject' => ['Permission denied.']]);
            }
            $topic->subject = $dto->subject;
        }

        if ($dto->locked !== null) {
            $topic->locked = (bool) $dto->locked;
        }
        if ($dto->sticky !== null) {
            $topic->sticky = (bool) $dto->sticky;
        }
        if ($dto->hlcolor !== null) {
            $topic->hlcolor = (int) max(0, $dto->hlcolor);
        }

        $topic->save();

        return $this->success(new TopicResource($topic->load('user', 'firstPost', 'lastPost')), 'Topic updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return array<string, mixed>
     */
    public function destroy(Topic $topic): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        app(CurrentUser::class)->set($user->toLegacyArray());

        if (! SupportForum::isModerator((int) $topic->id, 'topic') && ! Permission::can(PermissionEnum::POST_MANAGE, $user)) {
            throw ValidationException::withMessages(['topic' => ['Permission denied.']]);
        }

        $postCount = ForumRepository::countTopicPosts((int) $topic->id);
        ForumRepository::deleteTopic((int) $topic->id, (int) $topic->forumid, $postCount);

        return $this->success(['success' => true], 'Topic deleted');
    }
}
