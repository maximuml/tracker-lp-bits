<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BitbucketPublic;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

/**
 * Usercp lookup repository: read-only dropdown options and per-user
 * stat counts used to build the user control panel pages.
 */
final class UsercpLookupRepository extends BaseRepository
{
    public function getCommentCount(int $userId): int
    {
        return (int) Comment::query()->where('user', $userId)->count();
    }

    public function getForumPostCount(int $userId): int
    {
        return (int) Post::query()->where('userid', $userId)->count();
    }

    public function getTotalPostCount(): int
    {
        return (int) Post::query()->count();
    }

    public function getTopicPostCount(int $topicId): int
    {
        return (int) Post::query()->where('topicid', $topicId)->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReadTopics(int $userId, int $limit = 5): array
    {
        return DB::table('readposts')
            ->join('topics', 'topics.id', '=', 'readposts.topicid')
            ->where('readposts.userid', $userId)
            ->orderByDesc('readposts.id')
            ->limit($limit)
            ->get(['topics.id as id', 'topics.userid', 'topics.subject', 'topics.lastpost', 'topics.views'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int, \stdClass>
     */
    public function getCountryOptions(): array
    {
        return DB::table('countries')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * @return array<int, \stdClass>
     */
    public function getBitbucketOptions(): array
    {
        return DB::table('bitbucket')
            ->where('public', BitbucketPublic::YES->value)
            ->get()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function getStylesheetOptions(): array
    {
        return DB::table('stylesheets')
            ->orderBy('name')
            ->pluck('id', 'name')
            ->all();
    }
}
