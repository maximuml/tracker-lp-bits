<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * Topic moderation repository: move/delete and moderator flag mutations
 * (lock, sticky, highlight) for forum topics.
 */
class TopicModerationRepository extends BaseRepository
{
    public function moveTopic(int $topicid, int $newForumid, int $postCount, int $oldForumid): bool
    {
        if ($oldForumid == $newForumid) {
            return true;
        }

        Topic::query()->where('id', $topicid)->update(['forumid' => $newForumid]);
        Forum::query()->where('id', $oldForumid)->decrement('topiccount');
        Forum::query()->where('id', $oldForumid)->decrement('postcount', $postCount);
        Forum::query()->where('id', $newForumid)->increment('topiccount');
        Forum::query()->where('id', $newForumid)->increment('postcount', $postCount);

        return true;
    }

    public function deleteTopic(int $topicid, int $forumid, int $postCount): bool
    {
        Topic::query()->where('id', $topicid)->delete();
        Post::query()->where('topicid', $topicid)->delete();
        DB::table('readposts')->where('topicid', $topicid)->delete();
        Forum::query()->where('id', $forumid)->decrement('topiccount');
        Forum::query()->where('id', $forumid)->decrement('postcount', $postCount);

        return true;
    }

    public function updateTopicLocked(int $topicid, bool $locked): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['locked' => $locked]);
    }

    public function updateTopicSticky(int $topicid, bool $sticky): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['sticky' => $sticky]);
    }

    public function updateTopicHighlight(int $topicid, int $color): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['hlcolor' => $color]);
    }
}
