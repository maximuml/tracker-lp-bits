<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Contracts\Repositories\PostRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\OverforumRepository;
use App\Repositories\TopicReadStateRepository;
use App\Repositories\TopicRepository;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;
use App\Support\Forum;
use App\Support\Html\SafeHtml;
use App\Support\UserDisplay;
use App\ViewModels\Forum\ForumIndexViewModel;
use App\ViewModels\Forum\ForumRow;
use App\ViewModels\Forum\ForumStatsViewModel;
use App\ViewModels\Forum\LastPostRef;
use App\ViewModels\Forum\OverforumGroup;

/**
 * Builds the default forums index (overforums + forums list + stats)
 * and provides shared helpers used by the other forum page services.
 */
final class ForumIndexService
{
    /** @var array<int|string, mixed>|string|null Per-instance memo for getLastReadPostId(). */
    private array|string|null $lastReadPostList = null;

    public function __construct(
        private readonly ActorContext $actor,
        private readonly ForumRepositoryInterface $forumRepository,
        private readonly OverforumRepository $overforumRepository,
        private readonly NexusCache $cache,
        private readonly TopicRepository $topicRepository,
        private readonly TopicReadStateRepository $readStateRepository,
        private readonly PostRepositoryInterface $postRepository,
    ) {}

    /**
     * Build the default forums index (overforums + forums list + stats).
     */
    public function buildForumsIndex(ActorContext $actor, int $userId): ForumIndexViewModel
    {
        $Cache = $this->cache;
        $todayDate = date('Y-m-d');

        if ($actor->isAuthenticated()) {
            $this->forumRepository->updateUserForumAccess($actor->id, date('Y-m-d H:i:s'));
        }

        $SITENAME = SiteConfig::current()->basic->siteName();
        $showforumstatsMain = SiteConfig::current()->main->showForumStats();

        if (! $overforums = $Cache->get('overforums_list')) {
            $overforums = $this->overforumRepository->getOverforumsList();
            $Cache->put('overforums_list', $overforums, 86400);
        }
        $forums = $this->getForumRow(0) ?? [];
        $overforumIds = array_map(static fn (array $a): int => (int) ($a['id'] ?? 0), $overforums);
        $readable = static fn (array $f): bool => UserDisplay::currentClass() >= (int) ($f['minclassread'] ?? 0);
        $currentClass = UserDisplay::currentClass();

        $visibleForums = [];
        foreach ($forums as $f) {
            if ($readable($f)) {
                $visibleForums[] = $f;
            }
        }

        $prefetchKeys = [];
        foreach ($visibleForums as $f) {
            $fid = (int) ($f['id'] ?? 0);
            $prefetchKeys[] = 'forum_'.$fid.'_last_replied_topic_content';
            $prefetchKeys[] = 'forum_'.$fid.'_post_'.$todayDate.'_count';
        }
        $prefetched = $prefetchKeys === [] ? [] : $Cache->getMany($prefetchKeys);

        // Resolve per-forum data in batched passes — lazy per-forum
        // fallbacks used to cost ~3 queries per forum on a cold cache.
        $repliedByForum = [];
        $todayCounts = [];
        $missingReplied = [];
        $missingCounts = [];
        foreach ($visibleForums as $f) {
            $fid = (int) ($f['id'] ?? 0);
            $arr = $prefetched['forum_'.$fid.'_last_replied_topic_content'] ?? false;
            if ($arr) {
                $repliedByForum[$fid] = $arr;
            } else {
                $missingReplied[] = $fid;
            }
            $count = $prefetched['forum_'.$fid.'_post_'.$todayDate.'_count'] ?? false;
            if (is_numeric($count)) {
                $todayCounts[$fid] = (int) $count;
            } else {
                $missingCounts[] = $fid;
            }
        }
        if ($missingReplied !== []) {
            $batched = $this->topicRepository->getLastTopicsByForums($missingReplied);
            foreach ($missingReplied as $fid) {
                $arr = isset($batched[$fid]) ? $batched[$fid]->toArray() : false;
                $repliedByForum[$fid] = $arr;
                $Cache->put('forum_'.$fid.'_last_replied_topic_content', $arr, 900);
            }
        }
        $postIds = [];
        foreach ($repliedByForum as $arr) {
            if (is_array($arr) && ! empty($arr['lastpost'])) {
                $postIds[] = (int) $arr['lastpost'];
            }
        }
        $postIds = array_values(array_unique($postIds));
        $postRows = $postIds === [] ? [] : $Cache->getMany(array_map(static fn (int $id): string => 'post_'.$id.'_content', $postIds));
        $missingPostIds = [];
        foreach ($postIds as $id) {
            if (! is_array($postRows['post_'.$id.'_content'] ?? null)) {
                $missingPostIds[] = $id;
            }
        }
        if ($missingPostIds !== []) {
            $fetched = $this->postRepository->findPostArraysByIds($missingPostIds);
            foreach ($missingPostIds as $id) {
                $row = $fetched[$id] ?? null;
                $postRows['post_'.$id.'_content'] = $row;
                $Cache->put('post_'.$id.'_content', $row, 7200);
            }
        }
        if ($missingCounts !== []) {
            foreach ($this->postRepository->getForumTodayPostCounts($missingCounts, $todayDate) as $fid => $cnt) {
                $todayCounts[$fid] = $cnt;
            }
            foreach ($missingCounts as $fid) {
                $cnt = $todayCounts[$fid] ?? 0;
                $todayCounts[$fid] = $cnt;
                $Cache->put('forum_'.$fid.'_post_'.$todayDate.'_count', $cnt, 1800);
            }
        }

        $posterIds = [];
        foreach ($postRows as $row) {
            if (is_array($row)) {
                $posterIds[] = (int) ($row['userid'] ?? 0);
            }
        }
        UserDisplay::preload($posterIds);

        $sections = [];
        foreach ($overforums as $a) {
            if ($currentClass < (int) ($a['minclassview'] ?? 0)) {
                continue;
            }
            $forid = (int) $a['id'];
            $rows = [];
            foreach ($visibleForums as $f) {
                if ((int) ($f['forid'] ?? 0) === $forid) {
                    $rows[] = $this->forumRow($f, $actor, $repliedByForum, $postRows, $todayCounts);
                }
            }
            $sections[] = new OverforumGroup((string) ($a['name'] ?? ''), $rows);
        }

        $orphans = [];
        foreach ($visibleForums as $f) {
            if (! in_array((int) ($f['forid'] ?? 0), $overforumIds, true)) {
                $orphans[] = $this->forumRow($f, $actor, $repliedByForum, $postRows, $todayCounts);
            }
        }
        if ($orphans !== []) {
            $sections[] = new OverforumGroup((string) __('forums.col_forums'), $orphans);
        }

        return new ForumIndexViewModel(
            siteName: $SITENAME,
            canManageForums: Permission::can(PermissionEnum::FORUM_MANAGE),
            sections: $sections,
            stats: $showforumstatsMain ? $this->loadStats($todayDate) : null,
        );
    }

    /**
     * Build one forum row (name, counts, last post, moderators).
     *
     * @param  array<string, mixed>  $forums_arr
     * @param  array<int, array<string, mixed>|false>  $repliedByForum
     * @param  array<string, mixed>  $postRows  resolved post_*_content rows
     * @param  array<int, int>  $todayCounts  resolved forum_* post counts
     */
    private function forumRow(array $forums_arr, ActorContext $actor, array $repliedByForum, array $postRows, array $todayCounts): ForumRow
    {
        $forumid = (int) $forums_arr['id'];

        $forummoderators = Forum::moderatorsWithContext($forumid, false);

        $arr = $repliedByForum[$forumid] ?? false;

        $lastPost = null;
        $hasUnread = false;
        if ($arr) {
            $lastpostid = (int) $arr['lastpost'];
            $post_arr = $postRows['post_'.$lastpostid.'_content'] ?? null;
            if (! is_array($post_arr)) {
                $post_arr = [];
            }
            $lasttopicid = (int) $arr['id'];
            $fullSubject = (string) ($arr['subject'] ?? '');
            $displaySubject = $fullSubject;
            if (mb_strlen($displaySubject, 'UTF-8') > 35) {
                $displaySubject = mb_substr($displaySubject, 0, 33, 'UTF-8').'..';
            }
            $lastPost = new LastPostRef(
                topicId: $lasttopicid,
                subject: $displaySubject,
                fullSubject: $fullSubject,
                hlcolor: (int) $arr['hlcolor'],
                date: (string) ($post_arr['added'] ?? ''),
                poster: UserDisplay::username((int) ($post_arr['userid'] ?? 0)),
            );
            $hasUnread = $this->getLastReadPostId($lasttopicid, $actor) < $lastpostid;
        }

        $posttodaycount = $todayCounts[$forumid] ?? 0;

        return new ForumRow(
            id: $forumid,
            name: (string) ($forums_arr['name'] ?? ''),
            description: (string) ($forums_arr['description'] ?? ''),
            topicCount: (int) $forums_arr['topiccount'],
            postCount: (int) $forums_arr['postcount'],
            postsToday: (int) $posttodaycount,
            hasUnread: $hasUnread,
            lastPost: $lastPost,
            moderators: $forummoderators !== '' ? SafeHtml::fromTrustedHtml($forummoderators) : null,
        );
    }

    /**
     * Load the forum stats counters.
     */
    private function loadStats(string $todayDate): ForumStatsViewModel
    {
        $Cache = $this->cache;

        if (! $activeforumuser_num = $Cache->get('active_forum_user_count')) {
            $activeforumuser_num = $this->forumRepository->getActiveForumUserCount();
            $Cache->put('active_forum_user_count', $activeforumuser_num, 300);
        }
        if (! $postcount = $Cache->get('total_posts_count')) {
            $postcount = $this->postRepository->getTotalPostsCount();
            $Cache->put('total_posts_count', $postcount, 96400);
        }
        if (! $topiccount = $Cache->get('total_topics_count')) {
            $topiccount = $this->topicRepository->getTotalTopicsCount();
            $Cache->put('total_topics_count', $topiccount, 96500);
        }
        if (! $todaypostcount = $Cache->get('today_'.$todayDate.'_posts_count')) {
            $todaypostcount = $this->postRepository->getTodayPostsCount($todayDate);
            $Cache->put('today_'.$todayDate.'_posts_count', $todaypostcount, 700);
        }

        return new ForumStatsViewModel(
            posts: (int) $postcount,
            topics: (int) $topiccount,
            todayPosts: (int) $todaypostcount,
            activeUsers: (int) $activeforumuser_num,
        );
    }

    /**
     * Mark all topics as read for the current user.
     */
    public function catchUp(): void
    {
        if ($this->actor->isGuest()) {
            return;
        }

        $CURUSER = $this->actor->toLegacyArray();
        $Cache = $this->cache;
        $this->readStateRepository->clearReadPosts((int) $CURUSER['id']);
        $Cache->forget('user_'.$CURUSER['id'].'_last_read_post_list');
        $lastpostid = $this->postRepository->getLastPostId();
        if ($lastpostid) {
            $CURUSER['last_catchup'] = $lastpostid;
            $this->postRepository->updateLastCatchup((int) $CURUSER['id'], (int) $lastpostid);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getForumRow(int $forumid = 0): ?array
    {
        $Cache = $this->cache;
        if (! $forums = $Cache->get('forums_list')) {
            $forums = $this->forumRepository->getForumsList();
            $Cache->put('forums_list', $forums, 86400);
        }
        if (! $forumid) {
            return $forums;
        }

        return $forums[$forumid] ?? null;
    }

    public function getLastReadPostId(int $topicid, ActorContext $actor): int
    {
        $Cache = $this->cache;
        $ret = $this->lastReadPostList;
        if (! $ret && ! $ret = $Cache->get('user_'.$actor->id.'_last_read_post_list')) {
            $ret = $this->readStateRepository->getLastReadPosts($actor->id);
            if ($ret !== null) {
                $Cache->put('user_'.$actor->id.'_last_read_post_list', $ret, 900);
            } else {
                $Cache->put('user_'.$actor->id.'_last_read_post_list', 'no record', 900);
            }
        }
        $this->lastReadPostList = $ret;
        $lastCatchup = (int) $actor->value('last_catchup', 0);
        if (is_array($ret) && (isset($ret[$topicid])) && $lastCatchup < (int) $ret[$topicid]) {
            return (int) $ret[$topicid];
        } elseif ($lastCatchup) {
            return $lastCatchup;
        }

        return 0;
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function highlightColorOptions(string $selectColorLabel): array
    {
        $colors = [
            1 => 'Black', 2 => 'Sienna', 3 => 'Dark Olive Green', 4 => 'Dark Green',
            5 => 'Dark Slate Blue', 6 => 'Navy', 7 => 'Indigo', 8 => 'Dark Slate Gray',
            9 => 'Dark Red', 10 => 'Dark Orange', 11 => 'Olive', 12 => 'Green',
            13 => 'Teal', 14 => 'Blue', 15 => 'Slate Gray', 16 => 'Dim Gray',
            17 => 'Red', 18 => 'Sandy Brown', 19 => 'Yellow Green', 20 => 'Sea Green',
            21 => 'Medium Turquoise', 22 => 'Royal Blue', 23 => 'Purple', 24 => 'Gray',
            25 => 'Magenta', 26 => 'Orange', 27 => 'Yellow', 28 => 'Lime',
            29 => 'Cyan', 30 => 'Deep Sky Blue', 31 => 'Dark Orchid', 32 => 'Silver',
            33 => 'Pink', 34 => 'Wheat', 35 => 'Lemon Chiffon', 36 => 'Pale Green',
            37 => 'Pale Turquoise', 38 => 'Light Blue', 39 => 'Plum', 40 => 'White',
        ];
        $options = [['value' => 0, 'label' => $selectColorLabel]];
        foreach ($colors as $value => $name) {
            $options[] = ['value' => $value, 'label' => $name];
        }

        return $options;
    }
}
