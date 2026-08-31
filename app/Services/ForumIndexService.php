<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\ForumRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Forum;
use App\Support\Globals;
use App\Support\Palette;
use App\Support\Strings;
use App\Support\Time;
use App\Support\UserDisplay;

/**
 * Builds the default forums index (overforums + forums list + stats)
 * and provides shared helpers used by the other forum page services.
 */
final class ForumIndexService
{
    /**
     * Build the default forums index (overforums + forums list + stats).
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function buildForumsIndex(array $lang, array $curUser, int $userId): array
    {
        $Cache = app(LegacyRedisCache::class);
        $todayDate = date('Y-m-d');

        if ($curUser) {
            app(ForumRepository::class)->updateUserForumAccess((int) ($curUser['id'] ?? 0), date('Y-m-d H:i:s'));
        }

        $SITENAME = (string) app(Globals::class)->get('SITENAME', '');
        $showforumstatsMain = (string) app(Globals::class)->get('showforumstats_main', '');

        ob_start();
        echo '<h1 align="center">'.$SITENAME.'&nbsp;'.($lang['text_forums'] ?? '').'</h1>';
        echo '<p align="center"><a href="?action=search"><b>'.($lang['text_search'] ?? '').'</b></a> | <a href="?action=viewunread"><b>'.($lang['text_view_unread'] ?? '').'</b></a> | <a href="?catchup=1"><b>'.($lang['text_catch_up'] ?? '').'</b></a> '.(Permission::can(PermissionEnum::FORUM_MANAGE) ? '| <a href="forummanage.php"><b>'.($lang['text_forum_manager'] ?? '').'</b></a>' : '').'</p>';
        echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\">\n";

        if (! $overforums = $Cache?->get_value('overforums_list')) {
            $overforums = app(ForumRepository::class)->getOverforumsList();
            $Cache?->cache_value('overforums_list', $overforums, 86400);
        }
        foreach ($overforums as $a) {
            if (UserDisplay::currentClass() < (int) ($a['minclassview'] ?? 0)) {
                continue;
            }
            $forid = (int) $a['id'];
            $overforumname = (string) ($a['name'] ?? '');

            echo '<tr><td align="left" class="colhead" width="99%">'.htmlspecialchars($overforumname).'</td><td align="center" class="colhead">'.($lang['col_topics'] ?? '').'</td>'.
            '<td align="center" class="colhead">'.($lang['col_posts'] ?? '').'</td>'.
            '<td align="left" class="colhead">'.($lang['col_last_post'] ?? '').'</td><td class="colhead" align="left">'.($lang['col_moderator'] ?? '')."</td></tr>\n";

            $forums = $this->getForumRow(0);
            foreach ($forums ?? [] as $forums_arr) {
                if ((int) $forums_arr['forid'] != $forid) {
                    continue;
                }
                if (UserDisplay::currentClass() < (int) ($forums_arr['minclassread'] ?? 0)) {
                    continue;
                }

                $forumid = (int) $forums_arr['id'];
                $forumname = htmlspecialchars((string) ($forums_arr['name'] ?? ''));
                $forumdescription = htmlspecialchars((string) ($forums_arr['description'] ?? ''));

                $forummoderators = Forum::moderatorsWithContext($forumid, false);
                if (! $forummoderators) {
                    $forummoderators = '<a href="contactstaff.php"><i>'.($lang['text_apply_now'] ?? '').'</i></a>';
                }

                $topiccount = number_format((int) $forums_arr['topiccount']);
                $postcount = number_format((int) $forums_arr['postcount']);

                if (! $arr = $Cache?->get_value('forum_'.$forumid.'_last_replied_topic_content')) {
                    $lastTopic = app(ForumRepository::class)->getLastTopicByForum((int) $forumid);
                    $arr = $lastTopic ? $lastTopic->toArray() : false;
                    $Cache?->cache_value('forum_'.$forumid.'_last_replied_topic_content', $arr, 900);
                }

                if ($arr) {
                    $lastpostid = (int) $arr['lastpost'];
                    $post_arr = Forum::postRowWithContext($lastpostid);
                    $lastposterid = (int) ($post_arr['userid'] ?? 0);
                    $lastpostdate = Time::format($post_arr['added'] ?? '', true, false);
                    $lasttopicid = (int) $arr['id'];
                    $hlcolor = (int) $arr['hlcolor'];
                    $lasttopicdissubject = $lasttopicsubject = (string) ($arr['subject'] ?? '');
                    $max_length_of_topic_subject = 35;
                    $count_dispname = mb_strlen($lasttopicdissubject, 'UTF-8');
                    if ($count_dispname > $max_length_of_topic_subject) {
                        $lasttopicdissubject = mb_substr($lasttopicdissubject, 0, $max_length_of_topic_subject - 2, 'UTF-8').'..';
                    }
                    $lasttopic = $this->highlightTopic(htmlspecialchars($lasttopicdissubject), $hlcolor);

                    $lastpost = '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$lasttopicid.'&page=last#last').'" title="'.htmlspecialchars($lasttopicsubject).'">'.$lasttopic.'</a><br />'.$lastpostdate.'&nbsp;|&nbsp;'.UserDisplay::username($lastposterid);

                    $lastreadpost = $this->getLastReadPostId($lasttopicid, $curUser);

                    if ($lastreadpost >= $lastpostid) {
                        $img = $this->getTopicImage('read', $lang);
                    } else {
                        $img = $this->getTopicImage('unread', $lang);
                    }
                } else {
                    $lastpost = 'N/A';
                    $img = $this->getTopicImage('read', $lang);
                }
                $posttodaycount = $Cache?->get_value('forum_'.$forumid.'_post_'.$todayDate.'_count');
                if ($posttodaycount == '') {
                    $posttodaycount = app(ForumRepository::class)->getForumTodayPostCount((int) $forumid, date('Y-m-d'));
                    $Cache?->cache_value('forum_'.$forumid.'_post_'.$todayDate.'_count', $posttodaycount, 1800);
                }
                if ($posttodaycount > 0) {
                    $posttoday = '&nbsp;&nbsp;('.($lang['text_today'] ?? '').'<b><font class="new">'.$posttodaycount.'</font></b>)';
                } else {
                    $posttoday = '';
                }
                echo "<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\" style='padding-right: 10px'>".$img.'</td><td class="embedded"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid).'"><font class="big"><b>'.$forumname.'</b></font></a>'.$posttoday.
                '<br />'.$forumdescription.'</td></tr></table></td><td class="rowfollow" align="center" width="1%">'.$topiccount.'</td><td class="rowfollow" align="center" width="1%">'.$postcount.'</td>'.
                '<td class="rowfollow nowrap" align="left">'.$lastpost.'</td><td class="rowfollow" align="left">'.$forummoderators."</td></tr>\n";
            }
        }
        echo '</table>';
        if ($showforumstatsMain == 'yes') {
            echo $this->forumStats($lang, $todayDate);
        }

        return ['html' => (string) ob_get_clean()];
    }

    /**
     * Render the forum stats block.
     *
     * @param  array<string, mixed>  $lang
     */
    public function forumStats(array $lang, string $todayDate): string
    {
        $Cache = app(LegacyRedisCache::class);

        if (! $activeforumuser_num = $Cache?->get_value('active_forum_user_count')) {
            $activeforumuser_num = app(ForumRepository::class)->getActiveForumUserCount();
            $Cache?->cache_value('active_forum_user_count', $activeforumuser_num, 300);
        }
        if ($activeforumuser_num) {
            $forumusers = ($lang['text_there'] ?? '').Strings::isOrAre((int) $activeforumuser_num).'<b>'.$activeforumuser_num.'</b>'.($lang['text_online_user'] ?? '').Strings::addS((int) $activeforumuser_num).($lang['text_in_forum_now'] ?? '');
        } else {
            $forumusers = ($lang['text_no_active_users'] ?? '');
        }

        ob_start();
        ?>
<h2 align="left"><?php echo $lang['text_stats'] ?? '' ?></h2>
<table width="100%"><tr><td class="text">
<?php
        if (! $postcount = $Cache?->get_value('total_posts_count')) {
            $postcount = app(ForumRepository::class)->getTotalPostsCount();
            $Cache?->cache_value('total_posts_count', $postcount, 96400);
        }
        if (! $topiccount = $Cache?->get_value('total_topics_count')) {
            $topiccount = app(ForumRepository::class)->getTotalTopicsCount();
            $Cache?->cache_value('total_topics_count', $topiccount, 96500);
        }
        if (! $todaypostcount = $Cache?->get_value('today_'.$todayDate.'_posts_count')) {
            $todaypostcount = app(ForumRepository::class)->getTodayPostsCount($todayDate);
            $Cache?->cache_value('today_'.$todayDate.'_posts_count', $todaypostcount, 700);
        }
        echo ($lang['text_our_members_have'] ?? '').'<b>'.$postcount.'</b>'.($lang['text_posts_in_topics'] ?? '').'<b>'.$topiccount.'</b>'.($lang['text_in_topics'] ?? '').'<b><font class="new">'.$todaypostcount.'</font></b>'.($lang['text_new_post'] ?? '').Strings::addS((int) $todaypostcount).($lang['text_posts_today'] ?? '').'<br /><br />';
        echo $forumusers;
        ?>
</td></tr></table>
<?php
        return (string) ob_get_clean();
    }

    /**
     * Mark all topics as read for the current user.
     */
    public function catchUp(): void
    {
        $CURUSER = (array) (app(CurrentUser::class)->get() ?? []);
        $Cache = app(LegacyRedisCache::class);

        if (! $CURUSER) {
            return;
        }
        app(ForumRepository::class)->clearReadPosts((int) $CURUSER['id']);
        $Cache?->delete_value('user_'.$CURUSER['id'].'_last_read_post_list');
        $lastpostid = app(ForumRepository::class)->getLastPostId();
        if ($lastpostid) {
            $CURUSER['last_catchup'] = $lastpostid;
            app(ForumRepository::class)->updateLastCatchup((int) $CURUSER['id'], (int) $lastpostid);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getForumRow(int $forumid = 0): ?array
    {
        $Cache = app(LegacyRedisCache::class);
        if (! $forums = $Cache?->get_value('forums_list')) {
            $forums = app(ForumRepository::class)->getForumsList();
            $Cache?->cache_value('forums_list', $forums, 86400);
        }
        if (! $forumid) {
            return $forums;
        }

        return $forums[$forumid] ?? null;
    }

    /**
     * @param  array<string, mixed>  $curUser
     */
    public function getLastReadPostId(int $topicid, array $curUser): int
    {
        $Cache = app(LegacyRedisCache::class);
        static $ret = null;
        if (! $ret && ! $ret = $Cache?->get_value('user_'.($curUser['id'] ?? 0).'_last_read_post_list')) {
            $ret = app(ForumRepository::class)->getLastReadPosts((int) ($curUser['id'] ?? 0));
            if ($ret !== null) {
                $Cache?->cache_value('user_'.($curUser['id'] ?? 0).'_last_read_post_list', $ret, 900);
            } else {
                $Cache?->cache_value('user_'.($curUser['id'] ?? 0).'_last_read_post_list', 'no record', 900);
            }
        }
        if (is_array($ret) && (isset($ret[$topicid])) && (int) ($curUser['last_catchup'] ?? 0) < (int) $ret[$topicid]) {
            return (int) $ret[$topicid];
        } elseif ((int) ($curUser['last_catchup'] ?? 0)) {
            return (int) $curUser['last_catchup'];
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $lang
     */
    public function getTopicImage(string $status, array $lang): string
    {
        switch ($status) {
            case 'read':
                return '<img class="unlocked" src="pic/trans.gif" alt="read" title="'.($lang['title_read'] ?? '').'" />';
            case 'unread':
                return '<img class="unlockednew" src="pic/trans.gif" alt="unread" title="'.($lang['title_unread'] ?? '').'" />';
            case 'locked':
                return '<img class="locked" src="pic/trans.gif" alt="locked" title="'.($lang['title_locked'] ?? '').'" />';
            case 'lockednew':
                return '<img class="lockednew" src="pic/trans.gif" alt="lockednew" title="'.($lang['title_locked_new'] ?? '').'" />';
        }

        return '';
    }

    public function highlightTopic(string $subject, int $hlcolor): string
    {
        $colorname = Palette::forumHighlight($hlcolor);
        if ($colorname) {
            $subject = '<b><font color="'.$colorname.'">'.$subject.'</font></b>';
        }

        return $subject;
    }

    public function highlightColorOptions(string $selectColorLabel): string
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
        $cssNames = [
            1 => 'black', 2 => 'sienna', 3 => 'darkolivegreen', 4 => 'darkgreen',
            5 => 'darkslateblue', 6 => 'navy', 7 => 'indigo', 8 => 'darkslategray',
            9 => 'darkred', 10 => 'darkorange', 11 => 'olive', 12 => 'green',
            13 => 'teal', 14 => 'blue', 15 => 'slategray', 16 => 'dimgray',
            17 => 'red', 18 => 'sandybrown', 19 => 'yellowgreen', 20 => 'seagreen',
            21 => 'mediumturquoise', 22 => 'royalblue', 23 => 'purple', 24 => 'gray',
            25 => 'magenta', 26 => 'orange', 27 => 'yellow', 28 => 'lime',
            29 => 'cyan', 30 => 'deepskyblue', 31 => 'darkorchid', 32 => 'silver',
            33 => 'pink', 34 => 'wheat', 35 => 'lemonchiffon', 36 => 'palegreen',
            37 => 'paleturquoise', 38 => 'lightblue', 39 => 'plum', 40 => 'white',
        ];
        $out = "<option value='0'>".$selectColorLabel."</option>\n";
        foreach ($colors as $value => $name) {
            $out .= "<option style='background-color: ".$cssNames[$value]."' value=\"".$value.'">'.$name."</option>\n";
        }

        return $out;
    }
}
