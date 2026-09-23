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
        Forum::query()->where('id', $oldForumid)->update(['topiccount' => DB::raw('GREATEST(CAST(topiccount AS SIGNED) - 1, 0)')]);
        DB::update(
            'UPDATE `forums` SET `postcount` = GREATEST(CAST(`postcount` AS SIGNED) - ?, 0) WHERE `id` = ?',
            [$postCount, $oldForumid]
        );
        Forum::query()->where('id', $newForumid)->increment('topiccount');
        Forum::query()->where('id', $newForumid)->increment('postcount', $postCount);

        return true;
    }

    public function deleteTopic(int $topicid, int $forumid, int $postCount): bool
    {
        Topic::query()->where('id', $topicid)->delete();
        Post::query()->where('topicid', $topicid)->delete();
        DB::table('readposts')->where('topicid', $topicid)->delete();
        Forum::query()->where('id', $forumid)->update(['topiccount' => DB::raw('GREATEST(CAST(topiccount AS SIGNED) - 1, 0)')]);
        DB::update(
            'UPDATE `forums` SET `postcount` = GREATEST(CAST(`postcount` AS SIGNED) - ?, 0) WHERE `id` = ?',
            [$postCount, $forumid]
        );

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
