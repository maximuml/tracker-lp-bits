<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Topic;
use App\Support\Database;
use Illuminate\Support\Collection;

/**
 * Housekeeping queries on the topics table: forum recount mapping and the
 * old-topic locking sweep.
 */
final class TopicMaintenanceRepository extends BaseRepository
{
    /**
     * topic id => forum id map for the given forums (forum-maintenance
     * recount sweep).
     *
     * @param  Collection<int, int>|array<int>  $forumIds
     * @return Collection<int, int>
     */
    public function listForumIdById(Collection|array $forumIds): Collection
    {
        return Topic::query()->whereIn('forumid', $forumIds)->pluck('forumid', 'id');
    }

    public function lockNonStickyTopicsWithLastPostBefore(int $unixTimestampBefore): int
    {
        $postAddedField = Database::unixTimestampField('posts.added');

        return Topic::query()
            ->where('sticky', false)
            ->whereIn('lastpost', function ($query) use ($postAddedField, $unixTimestampBefore): void {
                $query->select('id')->from('posts')->whereRaw("{$postAddedField} < ?", [$unixTimestampBefore]);
            })
            ->update(['locked' => true]);
    }
}
