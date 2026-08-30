<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\DTOs\Forum\ListPostsDto;
use App\DTOs\Forum\StorePostDto;
use App\DTOs\Forum\UpdatePostDto;
use App\Enums\Permission\PermissionEnum;
use App\Http\Resources\PostResource;
use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function index(Request $request, Topic $topic): array
    {
        $this->setContextUser();

        $forum = $topic->forum;
        if (! $forum instanceof Forum || ! $this->canRead($forum)) {
            throw ValidationException::withMessages(['topic' => ['Permission denied.']]);
        }

        $dto = ListPostsDto::fromRequest($request);
        $posts = ForumRepository::getTopicPosts((int) $topic->id, null, $dto->offset(), $dto->perPage);

        return $this->success(PostResource::collection($posts));
    }

    /**
     * @return array<string, mixed>
     */
    public function store(Request $request, Topic $topic): array
    {
        $this->setContextUser();

        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        $forum = $topic->forum;
        if (! $forum instanceof Forum || ! $this->canWrite($forum)) {
            throw ValidationException::withMessages(['topic' => ['Permission denied.']]);
        }

        if ($topic->locked && ! $this->canModerate($topic)) {
            throw ValidationException::withMessages(['topic' => ['Topic is locked.']]);
        }

        $dto = StorePostDto::fromRequest($request);

        $date = now()->toDateTimeString();
        $postId = ForumRepository::createPost((int) $topic->id, (int) $user->id, $dto->body, $date);

        ForumRepository::setTopicLastPost((int) $topic->id, $postId);
        ForumRepository::incrementForumPostCount((int) $forum->id);
        ForumRepository::updateUserLastPost((int) $user->id, $date);

        $post = Post::query()->findOrFail($postId);
        $post->load('user');

        return $this->success(new PostResource($post), 'Post created');
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Topic $topic, Post $post): array
    {
        $this->setContextUser();

        $forum = $topic->forum;
        if (! $forum instanceof Forum || ! $this->canRead($forum)) {
            throw ValidationException::withMessages(['topic' => ['Permission denied.']]);
        }

        $post->load('user');

        return $this->success(new PostResource($post));
    }

    /**
     * @return array<string, mixed>
     */
    public function update(Request $request, Topic $topic, Post $post): array
    {
        $this->setContextUser();

        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        if (! $this->canEdit($post, $topic)) {
            throw ValidationException::withMessages(['post' => ['Permission denied.']]);
        }

        $dto = UpdatePostDto::fromRequest($request);

        $date = now()->toDateTimeString();
        ForumRepository::updatePostBody((int) $post->id, $dto->body, $date, (int) $user->id);

        $postInfo = ForumRepository::getPostEditInfo((int) $post->id);
        if ($dto->subject !== null && $dto->subject !== '' && ! empty($postInfo['is_first_post'])) {
            $topic->update(['subject' => $dto->subject]);
        }

        $post->refresh()->load('user');

        return $this->success(new PostResource($post), 'Post updated');
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Topic $topic, Post $post): array
    {
        $this->setContextUser();

        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        if (! $this->canModerate($topic) && (int) $post->userid !== (int) $user->id) {
            throw ValidationException::withMessages(['post' => ['Permission denied.']]);
        }

        ForumRepository::deletePost((int) $post->id, (int) $topic->id, (int) $topic->forumid);

        return $this->success(['success' => true], 'Post deleted');
    }

    private function setContextUser(): void
    {
        $user = Auth::user();
        if ($user instanceof User) {
            app(CurrentUser::class)->set($user->toLegacyArray());
        }
    }

    private function canRead(Forum $forum): bool
    {
        $user = Auth::user();
        $class = $user instanceof User ? (int) $user->class : 0;

        return $class >= (int) $forum->minclassread;
    }

    private function canWrite(Forum $forum): bool
    {
        $user = Auth::user();
        $class = $user instanceof User ? (int) $user->class : 0;

        return $class >= (int) $forum->minclasswrite;
    }

    private function canModerate(Topic $topic): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        return \App\Support\Forum::isModerator((int) $topic->id, 'topic')
            || Permission::can(PermissionEnum::POST_MANAGE, $user);
    }

    private function canEdit(Post $post, Topic $topic): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if ($this->canModerate($topic)) {
            return true;
        }

        $forum = $topic->forum;

        return (int) $post->userid === (int) $user->id && $forum instanceof Forum && $this->canWrite($forum);
    }
}
