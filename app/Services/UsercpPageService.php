<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Repositories\UsercpLookupRepository;
use App\Repositories\UsercpRepository;
use App\Support\AssetAppender;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Forum;
use App\Support\Globals;
use App\Support\Network;
use App\Support\Strings;
use App\Support\Time;
use App\Support\Url;
use App\Support\UserDisplay;
use App\ViewModels\UsercpPageViewModel;

/**
 * Prepares section data for the user control panel, replacing the legacy
 * usercp_content.php partial with typed Blade-rendered sections.
 *
 * Sections:
 *  - home: dashboard with stats, seed box, tokens, recently read topics
 *  - personal: personal settings form
 *  - tracker: tracker/browse settings form
 *  - forum: forum settings form
 *  - security: security settings form (with optional confirm step)
 */
final class UsercpPageService
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
        private readonly LegacyRedisCache $cache,
        private readonly UsercpRepository $usercpRepository,
        private readonly UsercpLookupRepository $usercpLookupRepository,
        private readonly UsercpTokenSectionBuilder $tokenSectionBuilder,
        private readonly UsercpSecuritySectionBuilder $securitySectionBuilder,
        private readonly UsercpTrackerSectionBuilder $trackerSectionBuilder,
        private readonly UsercpPersonalSectionBuilder $personalSectionBuilder,
        private readonly UsercpForumSectionBuilder $forumSectionBuilder,
    ) {}

    /**
     * Build the data for the requested section.
     */
    public function build(string $action, string $type): UsercpPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $lang = (array) ($this->globals->get('lang_usercp') ?? []);
        $cache = $this->cache;
        $userInfo = $this->usercpRepository->getUserById((int) ($curUser['id'] ?? 0));
        $siteName = Setting::getSiteName();

        $data = [
            'lang' => $lang,
            'curUser' => $curUser,
            'userInfo' => $userInfo,
            'siteName' => $siteName,
            'action' => $action,
            'type' => $type,
            'contentWidth' => (string) ($this->globals->get('CONTENT_WIDTH', '737')),
        ];

        switch ($action) {
            case 'personal':
                $data['personal'] = $this->personalSectionBuilder->build($lang, $curUser);
                break;
            case 'tracker':
                $data['tracker'] = $this->trackerSectionBuilder->build($lang, $curUser);
                break;
            case 'forum':
                $data['forum'] = $this->forumSectionBuilder->build($lang, $curUser);
                break;
            case 'security':
                $data['security'] = $this->securitySectionBuilder->build($lang, $curUser, $type);
                break;
            default:
                $data['home'] = $this->buildHome($lang, $curUser, $cache, $userInfo);
                break;
        }

        AssetAppender::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);

        return new UsercpPageViewModel(
            lang: $data['lang'],
            curUser: $data['curUser'],
            userInfo: $data['userInfo'],
            siteName: $data['siteName'],
            action: $data['action'],
            type: $data['type'],
            contentWidth: $data['contentWidth'],
            personal: $data['personal'] ?? null,
            tracker: $data['tracker'] ?? null,
            forum: $data['forum'] ?? null,
            security: $data['security'] ?? null,
            home: $data['home'] ?? null,
        );
    }

    /**
     * Build the home dashboard section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildHome(array $lang, array $curUser, ?LegacyRedisCache $cache, User $userInfo): array
    {
        $userId = (int) ($curUser['id'] ?? 0);

        // Comment count
        $commentCount = $this->usercpLookupRepository->getCommentCount($userId);

        // Join date
        $added = (string) ($curUser['added'] ?? '');
        if ($added === '0000-00-00 00:00:00' || $added === '') {
            $joinDate = 'N/A';
        } else {
            $joinDate = $added.' ('.Time::format($added, true, false, true).')';
        }

        // Forum posts + percentage
        $forumPosts = 0;
        $dayPosts = 0;
        $percentages = '';
        if ($cache !== null) {
            $cached = $cache->get_value('user_'.$userId.'_post_count');
            if ($cached !== false) {
                $forumPosts = (int) $cached;
            }
        }
        if ($forumPosts === 0) {
            $forumPosts = $this->usercpLookupRepository->getForumPostCount($userId);
            if ($cache !== null) {
                $cache->cache_value('user_'.$userId.'_post_count', $forumPosts, 3600);
            }
        }
        if ($forumPosts > 0) {
            $seconds = (int) ((time() - strtotime($added)) ?: 0);
            $days = (int) round($seconds / 86400);
            if ($days > 1) {
                $dayPosts = (int) round($forumPosts / $days, 1);
            }
            $postCount = 0;
            if ($cache !== null) {
                $cachedTotal = $cache->get_value('total_posts_count');
                if ($cachedTotal !== false) {
                    $postCount = (int) $cachedTotal;
                }
            }
            if ($postCount === 0) {
                $postCount = $this->usercpLookupRepository->getTotalPostCount();
                if ($cache !== null) {
                    $cache->cache_value('total_posts_count', $postCount, 96400);
                }
            }
            if ($postCount > 0) {
                $percentages = round($forumPosts * 100 / $postCount, 3).'%';
            }
        }

        // IP location
        $enableLocationTweak = (string) $this->globals->get('enablelocation_tweak', '') === 'yes';
        $ipLocation = '';
        if ($enableLocationTweak) {
            [$locPub, $locMod] = Network::ipLocationWithContext((string) ($curUser['ip'] ?? ''));
            $ipLocation = Strings::hidden((string) ($curUser['ip'] ?? '')." <span title='".$locMod."'>[".$locPub.']</span>');
        } else {
            $ipLocation = Strings::hidden((string) ($curUser['ip'] ?? ''));
        }

        // Passkey login form (if passkey login enabled and deadline in future)
        // The form embeds a server-generated HMAC signature (passkey + timestamp,
        // signed with login_secret) so the passkeyLogin controller can verify
        // authenticity without exposing the secret to the client.
        $passkeyLoginForm = '';
        $siteConfig = SiteConfig::current();
        $loginSecretDeadline = $siteConfig->security->loginSecretDeadline();
        if ($siteConfig->security->loginType() === 'passkey'
            && $loginSecretDeadline !== null
            && $loginSecretDeadline > date('Y-m-d H:i:s')
        ) {
            $passkey = (string) ($curUser['passkey'] ?? '');
            $timestamp = time();
            $signature = hash_hmac('sha256', $passkey.$timestamp, $siteConfig->security->loginSecret());
            $passkeyLoginForm = sprintf(
                '<form method="POST" action="%s/%s" style="display:inline"><input type="hidden" name="passkey" value="%s"><input type="hidden" name="timestamp" value="%d"><input type="hidden" name="signature" value="%s"><button type="submit" class="btn" style="font-size:inherit;padding:0 4px">%s</button></form>',
                Url::schemeAndHost(false),
                $siteConfig->security->loginSecret(),
                htmlspecialchars($passkey, ENT_QUOTES),
                $timestamp,
                htmlspecialchars($signature, ENT_QUOTES),
                $lang['text_passkey_login'] ?? 'Login'
            );
        }

        // Tokens
        $tokens = $this->tokenSectionBuilder->build($lang, $userInfo);

        // Recently read topics
        $readTopics = $this->buildReadTopics($lang, $userId, $cache);

        $avatarHtml = '';
        if (! empty($curUser['avatar'])) {
            $avatarHtml = '<img src="'.htmlspecialchars((string) $curUser['avatar']).'" border=0>';
        }

        $invitesHtml = ((int) ($curUser['invites'] ?? 0)).' [<a href="invite.php?id='.$userId.'" title="'.($lang['link_send_invitation'] ?? '').'">'.htmlspecialchars($lang['text_send'] ?? '').'</a>]';
        $karmaHtml = ((string) ($curUser['seedbonus'] ?? '0')).' [<a href="mybonus.php" title="'.($lang['link_use_karma_points'] ?? '').'">'.htmlspecialchars($lang['text_use'] ?? '').'</a>]';
        $commentsHtml = $commentCount.' [<a href="userhistory.php?action=viewcomments&id='.$userId.'" title="'.($lang['link_view_comments'] ?? '').'">'.htmlspecialchars($lang['text_view'] ?? '').'</a>]';

        $forumPostsHtml = null;
        if ($forumPosts > 0) {
            $forumPostsHtml = $forumPosts.' [<a href="userhistory.php?action=viewposts&id='.$userId.'" title="'.($lang['link_view_posts'] ?? '').'">'.htmlspecialchars($lang['text_view'] ?? '').'</a>] ('.$dayPosts.htmlspecialchars($lang['text_posts_per_day'] ?? '').'; '.$percentages.htmlspecialchars($lang['text_of_total_posts'] ?? '').')';
        }

        return [
            'commentCount' => $commentCount,
            'joinDate' => $joinDate,
            'forumPosts' => $forumPosts,
            'dayPosts' => $dayPosts,
            'percentages' => $percentages,
            'ipLocation' => $ipLocation,
            'passkeyLoginForm' => $passkeyLoginForm,
            'tokens' => $tokens,
            'readTopics' => $readTopics,
            'showAvatar' => ! empty($curUser['avatar']),
            'avatarUrl' => (string) ($curUser['avatar'] ?? ''),
            'passkey' => Strings::hidden((string) ($curUser['passkey'] ?? '')),
            'email' => (string) ($curUser['email'] ?? ''),
            'invites' => (int) ($curUser['invites'] ?? 0),
            'seedbonus' => (string) ($curUser['seedbonus'] ?? '0'),
            'avatarHtml' => $avatarHtml,
            'invitesHtml' => $invitesHtml,
            'karmaHtml' => $karmaHtml,
            'commentsHtml' => $commentsHtml,
            'forumPostsHtml' => $forumPostsHtml,
        ];
    }

    /**
     * Build recently read topics section data.
     *
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildReadTopics(array $lang, int $userId, ?LegacyRedisCache $cache): array
    {
        $topicRows = $this->usercpLookupRepository->getReadTopics($userId);
        $items = [];
        foreach ($topicRows as $topicArr) {
            $topicId = (int) $topicArr['id'];
            $topicViews = (int) $topicArr['views'];
            $views = number_format($topicViews);

            $posts = 0;
            if ($cache !== null) {
                $cached = $cache->get_value('topic_'.$topicId.'_post_count');
                if ($cached !== false) {
                    $posts = (int) $cached;
                }
            }
            if ($posts === 0) {
                $posts = $this->usercpLookupRepository->getTopicPostCount($topicId);
                if ($cache !== null) {
                    $cache->cache_value('topic_'.$topicId.'_post_count', $posts, 3600);
                }
            }
            $replies = max(0, $posts - 1);

            $arr = Forum::postRowWithContext((int) $topicArr['lastpost']);
            $postid = (int) ($arr['id'] ?? 0);
            $userid = (int) ($arr['userid'] ?? 0);
            $added = (string) (Time::format((string) ($arr['added'] ?? ''), true, false) ?? '');

            $items[] = [
                'id' => $topicId,
                'subject' => (string) $topicArr['subject'],
                'userid' => (int) $topicArr['userid'],
                'views' => $views,
                'replies' => $replies,
                'author' => UserDisplay::username((int) $topicArr['userid']),
                'lastPostId' => $postid,
                'lastPostUserId' => $userid,
                'lastPostUsername' => UserDisplay::username($userid),
                'lastPostAdded' => $added,
            ];
        }

        return [
            'items' => $items,
            'colTopicTitle' => $lang['col_topic_title'] ?? 'Topic title',
            'colReplies' => $lang['col_replies'] ?? 'Replies',
            'colViews' => $lang['col_views'] ?? 'Views',
            'colTopicStarter' => $lang['col_topic_starter'] ?? 'Topic starter',
            'colLastPost' => $lang['col_last_post'] ?? 'Last post',
            'title' => $lang['text_recently_read_topics'] ?? 'Recently read topics',
        ];
    }
}
