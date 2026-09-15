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
     * @param  array<string, mixed>  $lang
     * @return list<array<string, mixed>>
     */
    public function build(int $limit, ?string $subtype, array $lang): array
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
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_topic'] ?? ' Forum Topic Starters '),
                'limits' => [100, 250],
                'subtype' => 'mtop',
            ];
        }

        if ($limit === 10 || $subtype === 'mpos') {
            $sections[] = [
                'view' => 'posts',
                'data' => $this->toArray((clone $postBase)->orderBy('userposts', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_post'] ?? ' Forum Posters '),
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
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_commenter'] ?? 'Torrent Commenter '),
                'limits' => [100, 250],
                'subtype' => 'mcmt',
                'what' => $lang['col_comments'] ?? 'Comments',
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
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_biggest_topics'] ?? 'Biggest Topics'),
                'limits' => [100, 250],
                'subtype' => 'btop',
            ];
        }

        return $sections;
    }
}
