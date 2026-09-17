<?php

declare(strict_types=1);

namespace App\Repositories\Topten;

use Illuminate\Support\Facades\DB;

/**
 * Top-ten forum/community leaderboard sections — extracted from ToptenRepository to keep both
 * classes under the 400-line ratchet.
 */
final class CommunitySections extends SectionQueries
{
    /**
     * @return list<array<string, mixed>>
     */
    public function build(int $limit, ?string $subtype): array
    {
        $sections = [];

        $postBase = DB::table('users as u')
            ->leftJoin('topics', 'u.id', '=', 'topics.userid')
            ->leftJoin('posts', 'u.id', '=', 'posts.userid')
            ->select('u.id as userid', DB::raw('COUNT(DISTINCT topics.id) as usertopics'), DB::raw('COUNT(DISTINCT posts.id) as userposts'))
            ->groupBy('u.id');

        if ($limit === 10 || $subtype === 'mtop') {
            $sections[] = [
                'view' => 'posts',
                'data' => $this->toArray((clone $postBase)->orderBy('usertopics', 'desc')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_most_topic')),
                'limits' => [100, 250],
                'subtype' => 'mtop',
            ];
        }

        if ($limit === 10 || $subtype === 'mpos') {
            $sections[] = [
                'view' => 'posts',
                'data' => $this->toArray((clone $postBase)->orderBy('userposts', 'desc')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_most_post')),
                'limits' => [100, 250],
                'subtype' => 'mpos',
            ];
        }

        if ($limit === 10 || $subtype === 'mcmt') {
            $sections[] = [
                'view' => 'comments',
                'data' => $this->toArray(
                    DB::table('users')
                        ->leftJoin('comments', 'users.id', '=', 'comments.user')
                        ->select('users.id as userid', DB::raw('COUNT(comments.id) as num'))
                        ->groupBy('users.id')
                        ->orderBy('num', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_most_commenter')),
                'limits' => [100, 250],
                'subtype' => 'mcmt',
                'what' => __('legacy/topten.col_comments'),
            ];
        }

        if ($limit === 10 || $subtype === 'btop') {
            $sections[] = [
                'view' => 'bigtopic',
                'data' => $this->toArray(
                    DB::table('topics as tp')
                        ->leftJoin('posts', 'tp.id', '=', 'posts.topicid')
                        ->leftJoin('forums', 'tp.forumid', '=', 'forums.id')
                        ->select('tp.id as topicid', 'tp.subject as topicsubject', DB::raw('COUNT(posts.id) as postnum'), 'tp.forumid', 'forums.id as forumid')
                        ->where('forums.minclassread', '<=', 1)
                        ->orWhereNull('forums.id')
                        ->groupBy('tp.id', 'tp.subject', 'tp.forumid', 'forums.id')
                        ->orderBy('postnum', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_biggest_topics')),
                'limits' => [100, 250],
                'subtype' => 'btop',
            ];
        }

        return $sections;
    }
}
