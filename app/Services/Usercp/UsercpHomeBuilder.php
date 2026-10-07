<?php

declare(strict_types=1);

namespace App\Services\Usercp;

use App\Contracts\Repositories\UsercpLookupRepositoryInterface;
use App\Contracts\Repositories\UsercpRepositoryInterface;
use App\Models\User;
use App\Repositories\TokenRepository;
use App\Support\AssetAppender;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;
use App\Support\Forum;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\Support\Network;
use App\Support\Strings;
use App\Support\Url;
use App\Support\UserDisplay;
use App\ViewModels\Usercp\PasskeyLoginForm;
use App\ViewModels\Usercp\ReadTopicItem;
use App\ViewModels\Usercp\UsercpHomeSection;
use App\ViewModels\Usercp\UsercpPostStats;
use App\ViewModels\Usercp\UsercpTokenSection;

/**
 * Builds the usercp "home" dashboard section: stats, seed box, tokens,
 * recently read topics. Split out of UsercpPageService per-tab.
 */
final class UsercpHomeBuilder
{
    public function __construct(
        private readonly UsercpLookupRepositoryInterface $usercpLookupRepository,
        private readonly UsercpRepositoryInterface $usercpRepository,
        private readonly TokenRepository $tokenRepository,
        private readonly ?NexusCache $cache
    ) {}

    /**
     * @param  array<string, mixed>  $curUser
     */
    public function build(array $curUser, User $userInfo): UsercpHomeSection
    {
        $cache = $this->cache;
        $userId = (int) ($curUser['id'] ?? 0);

        // Comment count
        $commentCount = $this->usercpLookupRepository->getCommentCount($userId);

        // Join date (raw — the view renders it through <x-time>)
        $added = (string) ($curUser['added'] ?? '');

        // Forum posts + percentage
        $forumPosts = 0;
        $dayPosts = 0;
        $percentages = '';
        if ($cache !== null) {
            $cached = $cache->get('user_'.$userId.'_post_count');
            if ($cached !== false) {
                $forumPosts = (int) $cached;
            }
        }
        if ($forumPosts === 0) {
            $forumPosts = $this->usercpLookupRepository->getForumPostCount($userId);
            if ($cache !== null) {
                $cache->put('user_'.$userId.'_post_count', $forumPosts, 3600);
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
                $cachedTotal = $cache->get('total_posts_count');
                if ($cachedTotal !== false) {
                    $postCount = (int) $cachedTotal;
                }
            }
            if ($postCount === 0) {
                $postCount = $this->usercpLookupRepository->getTotalPostCount();
                if ($cache !== null) {
                    $cache->put('total_posts_count', $postCount, 96400);
                }
            }
            if ($postCount > 0) {
                $percentages = round($forumPosts * 100 / $postCount, 3).'%';
            }
        }

        // IP location
        $enableLocationTweak = SiteConfig::current()->tweak->enableLocation();
        $ipLocation = '';
        if ($enableLocationTweak) {
            [$locPub, $locMod] = Network::ipLocationWithContext((string) ($curUser['ip'] ?? ''));
            $ipLocation = Strings::hidden(e((string) ($curUser['ip'] ?? ''))." <span title='".e($locMod, false)."'>[".e($locPub).']</span>');
        } else {
            $ipLocation = Strings::hidden(e((string) ($curUser['ip'] ?? '')));
        }

        // Passkey login form data (if passkey login enabled and deadline in
        // future). The form embeds a server-generated HMAC signature (passkey +
        // timestamp, signed with login_secret) so the passkeyLogin controller
        // can verify authenticity without exposing the secret to the client.
        $passkeyLoginForm = null;
        $siteConfig = SiteConfig::current();
        $loginSecretDeadline = $siteConfig->security->loginSecretDeadline();
        if ($siteConfig->security->loginType() === 'passkey'
            && $loginSecretDeadline !== null
            && $loginSecretDeadline > date('Y-m-d H:i:s')
        ) {
            $passkey = (string) ($curUser['passkey'] ?? '');
            $timestamp = time();
            $signature = hash_hmac('sha256', $passkey.$timestamp, $siteConfig->security->loginSecret());
            $passkeyLoginForm = new PasskeyLoginForm(
                action: Url::schemeAndHost(false).'/'.$siteConfig->security->loginSecret(),
                passkey: $passkey,
                timestamp: $timestamp,
                signature: $signature,
            );
        }

        // Tokens
        $tokens = $this->buildTokenSection($userInfo);

        // Recently read topics
        $readTopics = $this->buildReadTopics($userId, $cache);

        $passkeyLogin = $passkeyLoginForm;

        return new UsercpHomeSection(
            joinDate: ($added === '0000-00-00 00:00:00' || $added === '') ? null : $added,
            email: (string) ($curUser['email'] ?? ''),
            ipLocation: SafeHtml::fromTrustedHtml($ipLocation),
            showAvatar: ! empty($curUser['avatar']),
            avatarUrl: (string) ($curUser['avatar'] ?? ''),
            passkey: Strings::hidden(e((string) ($curUser['passkey'] ?? ''))),
            passkeyLogin: $passkeyLogin,
            invites: (int) ($curUser['invites'] ?? 0),
            seedbonus: (string) ($curUser['seedbonus'] ?? '0'),
            commentCount: $commentCount,
            forumPosts: $forumPosts > 0
                ? new UsercpPostStats(posts: $forumPosts, dayPosts: $dayPosts, percentages: $percentages)
                : null,
            tokens: $tokens,
            readTopics: $readTopics,
            userId: $userId,
            // Link titles: decode `&nbsp;`-style entities so the view can
            // put them in `title` attributes through plain `{{ }}`.
            invitesLinkTitle: html_entity_decode((string) __('usercp.link_send_invitation'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            karmaLinkTitle: html_entity_decode((string) __('usercp.link_use_karma_points'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            commentsLinkTitle: html_entity_decode((string) __('usercp.link_view_comments'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            postsLinkTitle: html_entity_decode((string) __('usercp.link_view_posts'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
    }

    /**
     * Build recently read topics section data.
     *
     * @return list<ReadTopicItem>
     */
    private function buildReadTopics(int $userId, ?NexusCache $cache): array
    {
        $topicRows = $this->usercpLookupRepository->getReadTopics($userId);
        $postCounts = [];
        $uncachedTopicIds = [];
        $readTopicIds = array_map(fn ($topicArr) => (int) $topicArr['id'], $topicRows);
        $cachedCounts = $readTopicIds === []
            ? []
            : ($cache?->getMany(array_map(fn ($id) => 'topic_'.$id.'_post_count', $readTopicIds)) ?? []);
        foreach ($readTopicIds as $topicId) {
            $cached = $cachedCounts['topic_'.$topicId.'_post_count'] ?? false;
            if ($cached !== false) {
                $postCounts[$topicId] = (int) $cached;
            } else {
                $uncachedTopicIds[] = $topicId;
            }
        }
        if ($uncachedTopicIds !== []) {
            $postCounts += $this->usercpLookupRepository->getTopicPostCounts($uncachedTopicIds);
            foreach ($uncachedTopicIds as $topicId) {
                $cache?->put('topic_'.$topicId.'_post_count', $postCounts[$topicId] ?? 0, 3600);
            }
        }
        $lastPostIds = [];
        foreach ($topicRows as $topicArr) {
            if (! empty($topicArr['lastpost'])) {
                $lastPostIds[] = (int) $topicArr['lastpost'];
            }
        }
        $lastPostRows = $lastPostIds === []
            ? []
            : ($cache?->getMany(array_map(fn ($id) => 'post_'.$id.'_content', array_values(array_unique($lastPostIds)))) ?? []);
        $items = [];
        foreach ($topicRows as $topicArr) {
            $topicId = (int) $topicArr['id'];
            $topicViews = (int) $topicArr['views'];

            $posts = $postCounts[$topicId] ?? 0;

            $arr = $lastPostRows['post_'.((int) $topicArr['lastpost']).'_content'] ?? false;
            if (! is_array($arr)) {
                $arr = Forum::postRowWithContext((int) $topicArr['lastpost']) ?? [];
            }
            $userid = (int) ($arr['userid'] ?? 0);

            $items[] = new ReadTopicItem(
                id: $topicId,
                subject: (string) $topicArr['subject'],
                views: number_format($topicViews),
                replies: max(0, $posts - 1),
                author: UserDisplay::username((int) $topicArr['userid']),
                lastPostAdded: ($added = (string) ($arr['added'] ?? '')) !== '' ? $added : null,
                lastPostUsername: UserDisplay::username($userid),
            );
        }

        return $items;
    }

    private function buildTokenSection(User $userInfo): UsercpTokenSection
    {
        $permissions = [];
        foreach ($this->tokenRepository->listUserTokenPermissionAllowed() as $name => $permLabel) {
            $permissions[] = ['value' => (string) $name, 'label' => (string) $permLabel];
        }

        $items = [];
        foreach ($this->usercpRepository->getUserTokens($userInfo) as $tokenRecord) {
            $items[] = [
                'id' => (int) $tokenRecord['id'],
                'name' => (string) $tokenRecord['name'],
                'abilities' => (string) $tokenRecord['abilitiesText'],
                'createdAt' => (string) $tokenRecord['created_at'],
            ];
        }

        $label = Locale::trans('token.label', [], null);
        $columnName = Locale::trans('label.name', [], null);
        $columnPermission = Locale::trans('token.permission', [], null);
        $columnCreatedAt = Locale::trans('label.created_at', [], null);
        $actionLabel = Locale::trans('label.action', [], null);
        $actionCreate = Locale::trans('label.create', [], null);
        $deleteLabel = __('functions.text_delete');
        $confirmRemoveLabel = __('functions.std_confirm_remove');

        $tokLabel = addslashes($label);
        $tokCreate = addslashes($actionCreate);
        $tokConfirmRemove = addslashes($confirmRemoveLabel);
        $tokenJs = <<<JS
document.getElementById('add-token-box-btn').addEventListener('click', function () {
    layer.open({
        type: 1,
        title: "{$tokLabel} {$tokCreate}",
        content: document.getElementById('token-form-template').content,
        btn: ['OK'],
        btnAlign: 'c',
        yes: function (index) {
            layer.close(index);
            var form = document.getElementById('token-box-form');
            var params = serializeForm(form);
            nativePost('/web/token/add', params, function (response) {
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg, window.nexusLayerOptions.alert)
                } else {
                    layer.alert(response.msg, window.nexusLayerOptions.alert, function(index) {
                        layer.close(index);
                        window.location.reload()
                    })
                }
            })
        }
    })
});
var tokenTableEl = document.getElementById('token-table');
if (tokenTableEl) {
    tokenTableEl.addEventListener('click', function (e) {
        if (!e.target || !e.target.classList || !e.target.classList.contains('token-del')) return;
        var params = {id: e.target.getAttribute("data-id")}
        layer.confirm("{$tokConfirmRemove}", window.nexusLayerOptions.confirm, function (index) {
            layer.close(index)
            nativePost('/web/token/del', params, function (response) {
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg, window.nexusLayerOptions.alert)
                    return
                }
                window.location.reload()
            })
        })
    });
}
JS;
        AssetAppender::js($tokenJs, 'footer', false);

        return new UsercpTokenSection(
            label: $label,
            columnName: $columnName,
            columnPermission: $columnPermission,
            columnCreatedAt: $columnCreatedAt,
            actionLabel: $actionLabel,
            actionCreate: $actionCreate,
            permissions: $permissions,
            items: $items,
            deleteLabel: (string) $deleteLabel,
            confirmRemoveLabel: (string) $confirmRemoveLabel,
        );
    }
}
