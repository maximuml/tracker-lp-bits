<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;

/**
 * Post lookups: shaped single-post reads for compose/edit/moderation views.
 */
class PostLookupRepository extends BaseRepository
{
    public function postExists(int $id): ?int
    {
        $post = Post::query()->where('id', $id)->first(['topicid']);

        return $post ? (int) $post->topicid : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPostForQuote(int $id): ?array
    {
        $post = Post::query()->where('id', $id)->first(['topicid', 'body', 'userid']);
        if (! $post) {
            return null;
        }
        $topic = Topic::query()->where('id', $post->topicid)->first(['subject']);
        $username = User::query()->where('id', $post->userid)->value('username');

        return [
            'topicid' => (int) $post->topicid,
            'body' => (string) $post->body,
            'userid' => (int) $post->userid,
            'username' => $username,
            'topic_subject' => $topic ? $topic->subject : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPostForEdit(int $id): ?array
    {
        $post = Post::query()->where('id', $id)->first(['topicid', 'body']);
        if (! $post) {
            return null;
        }
        $topicid = (int) $post->topicid;
        $firstpost = (int) Post::query()->where('topicid', $topicid)->min('id');
        $topic = Topic::query()->where('id', $topicid)->first(['subject']);

        return [
            'topicid' => $topicid,
            'body' => (string) $post->body,
            'firstpost' => $firstpost,
            'topic_subject' => $topic ? $topic->subject : null,
            'is_first_post' => $firstpost == $id,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPostWithTopic(int $postid): ?array
    {
        $post = Post::query()->where('id', $postid)->first(['userid', 'topicid']);
        if (! $post) {
            return null;
        }
        $topic = Topic::query()->where('id', $post->topicid)->first(['locked']);

        return [
            'userid' => (int) $post->userid,
            'topicid' => (int) $post->topicid,
            'locked' => $topic ? $topic->locked : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPostEditInfo(int $postid): ?array
    {
        $post = Post::query()->where('id', $postid)->first(['topicid']);
        if (! $post) {
            return null;
        }
        $topicid = (int) $post->topicid;
        $topic = Topic::query()->where('id', $topicid)->first(['forumid']);
        $firstpost = (int) Post::query()->where('topicid', $topicid)->min('id');

        return [
            'topicid' => $topicid,
            'forumid' => $topic ? (int) $topic->forumid : 0,
            'is_first_post' => $firstpost == $postid,
        ];
    }

    public function getPost(int $id): ?Post
    {
        return Post::query()->where('id', $id)->first();
    }

    public function getPostWithUser(int $id): ?Post
    {
        return Post::query()->with('user')->where('id', $id)->first();
    }

    public function getFirstPostId(int $topicid): int
    {
        return (int) Post::query()->where('topicid', $topicid)->min('id');
    }

    /**
     * @return array{topicid: int, userid: int}|null
     */
    public function getPostTopicAndUser(int $postid): ?array
    {
        $post = Post::query()->where('id', $postid)->first(['topicid', 'userid']);

        return $post ? [
            'topicid' => (int) $post->topicid,
            'userid' => (int) $post->userid,
        ] : null;
    }

    public function getPreviousPostId(int $topicid, int $postid): ?int
    {
        return Post::query()
            ->where('topicid', $topicid)
            ->where('id', '<', $postid)
            ->orderByDesc('id')
            ->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function getPostArrayById(int $id): array
    {
        return Post::query()->findOrFail($id)->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPostArrayById(int $id): ?array
    {
        $post = Post::query()->where('id', $id)->first();

        return $post ? $post->toArray() : null;
    }
}
