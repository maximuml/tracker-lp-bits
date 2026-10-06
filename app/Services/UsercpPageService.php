<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\UsercpLookupRepositoryInterface;
use App\Contracts\Repositories\UsercpRepositoryInterface;
use App\Enums\UserAcceptPms;
use App\Enums\UserAppendPromotion;
use App\Enums\UserClickTopic;
use App\Enums\UserFontsize;
use App\Enums\UserGender;
use App\Enums\UserPrivacy;
use App\Enums\UserTheme;
use App\Enums\UserTimeType;
use App\Enums\UserTooltip;
use App\Models\Setting;
use App\Models\TrackerUrl;
use App\Models\User;
use App\Repositories\StyleRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserPasskeyRepository;
use App\Support\AssetAppender;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Forum;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Html\SafeHtml;
use App\Support\Input;
use App\Support\LegacyYesNo;
use App\Support\Locale;
use App\Support\Network;
use App\Support\Strings;
use App\Support\TwoFactorAuthHelper;
use App\Support\Url;
use App\Support\UserDisplay;
use App\ViewModels\Search\SearchCategoryTableFactory;
use App\ViewModels\Usercp\PasskeyItem;
use App\ViewModels\Usercp\PasskeyLoginForm;
use App\ViewModels\Usercp\ReadTopicItem;
use App\ViewModels\Usercp\TwoStepState;
use App\ViewModels\Usercp\UsercpForumSection;
use App\ViewModels\Usercp\UsercpHomeSection;
use App\ViewModels\Usercp\UsercpPersonalSection;
use App\ViewModels\Usercp\UsercpPostStats;
use App\ViewModels\Usercp\UsercpSecuritySection;
use App\ViewModels\Usercp\UsercpTokenSection;
use App\ViewModels\Usercp\UsercpTrackerSection;
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
        private readonly UsercpRepositoryInterface $usercpRepository,
        private readonly UsercpLookupRepositoryInterface $usercpLookupRepository,
        private readonly UserPasskeyRepository $passkeyRepository,
        private readonly TokenRepository $tokenRepository,
        private readonly SearchCategoryTableFactory $searchCategoryTableFactory
    ) {}

    /**
     * Build the data for the requested section.
     */
    public function build(string $action, string $type): UsercpPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $cache = $this->cache;
        $userInfo = (new User)->newFromBuilder($curUser);
        $siteName = Setting::getSiteName();

        $data = [
            'curUser' => $curUser,
            'userInfo' => $userInfo,
            'siteName' => $siteName,
            'action' => $action,
            'type' => $type,
            'contentWidth' => (string) ($this->globals->get('CONTENT_WIDTH', '737')),
        ];

        switch ($action) {
            case 'personal':
                $data['personal'] = $this->buildPersonalSection($curUser);
                break;
            case 'tracker':
                $data['tracker'] = $this->buildTrackerSection($curUser);
                break;
            case 'forum':
                $data['forum'] = $this->buildForumSection($curUser);
                break;
            case 'security':
                $data['security'] = $this->buildSecuritySection($curUser, $type);
                break;
            default:
                $data['home'] = $this->buildHome($curUser, $cache, $userInfo);
                break;
        }

        return new UsercpPageViewModel(
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
     * @param  array<string, mixed>  $curUser
     */
    private function buildHome(array $curUser, ?LegacyRedisCache $cache, User $userInfo): UsercpHomeSection
    {
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
            invitesLinkTitle: html_entity_decode((string) __('legacy/usercp.link_send_invitation'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            karmaLinkTitle: html_entity_decode((string) __('legacy/usercp.link_use_karma_points'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            commentsLinkTitle: html_entity_decode((string) __('legacy/usercp.link_view_comments'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            postsLinkTitle: html_entity_decode((string) __('legacy/usercp.link_view_posts'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
    }

    /**
     * Build recently read topics section data.
     *
     * @return list<ReadTopicItem>
     */
    private function buildReadTopics(int $userId, ?LegacyRedisCache $cache): array
    {
        $topicRows = $this->usercpLookupRepository->getReadTopics($userId);
        $postCounts = [];
        $uncachedTopicIds = [];
        $readTopicIds = array_map(fn ($topicArr) => (int) $topicArr['id'], $topicRows);
        $cachedCounts = $readTopicIds === []
            ? []
            : ($cache?->get_values(array_map(fn ($id) => 'topic_'.$id.'_post_count', $readTopicIds)) ?? []);
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
                $cache?->cache_value('topic_'.$topicId.'_post_count', $postCounts[$topicId] ?? 0, 3600);
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
            : ($cache?->get_values(array_map(fn ($id) => 'post_'.$id.'_content', array_values(array_unique($lastPostIds)))) ?? []);
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

    /**
     * @param  array<string, mixed>  $curUser
     */
    private function buildForumSection(array $curUser): UsercpForumSection
    {
        return new UsercpForumSection(
            formId: 'form'.Strings::randomCode(6),
            showTooltipSetting: SiteConfig::current()->tweak->enableTooltip(),
            topicsPerPage: (int) ($curUser['topicsperpage'] ?? 0),
            postsPerPage: (int) ($curUser['postsperpage'] ?? 0),
            avatars: LegacyYesNo::isYes($curUser['avatars'] ?? null),
            signatures: LegacyYesNo::isYes($curUser['signatures'] ?? null),
            showLastPost: LegacyYesNo::isYes($curUser['showlastpost'] ?? null),
            clicktopic: UserClickTopic::tryFrom((int) ($curUser['clicktopic'] ?? 0))?->stringValue() ?? 'firstpage',
            signature: (string) ($curUser['signature'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $curUser
     */
    private function buildPersonalSection(array $curUser): UsercpPersonalSection
    {
        $countryOptions = ['0' => '---- '.__('legacy/usercp.select_none_selected').' ----'];
        foreach ($this->usercpLookupRepository->getCountryOptions() as $ct) {
            $countryOptions[(string) $ct->id] = (string) $ct->name;
        }

        $trackerUrlOptions = [];
        foreach (TrackerUrl::listAll() as $item) {
            $trackerUrlOptions[(string) $item->id] = (string) $item->url;
        }

        $baseUrl = Url::absolute(SiteConfig::current()->basic->baseUrl() ?: Input::serverValue('HTTP_HOST', 'localhost'));
        $defaultAvatarUrl = $baseUrl.'/pic/default_avatar.png';
        $bitbucketOptions = [];
        foreach ($this->usercpLookupRepository->getBitbucketOptions() as $sor) {
            $bitbucketOptions[$baseUrl.'/bitbucket/'.(string) $sor->name] = (string) $sor->name;
        }

        $notifs = (string) ($curUser['notifs'] ?? '');
        $notifCheckboxes = [];
        foreach (User::$notificationOptions as $option) {
            $notifCheckboxes[] = [
                'name' => 'notifs['.$option.']',
                'checked' => is_null($curUser['notifs'] ?? null) || str_contains($notifs, "[{$option}]"),
                'label' => (string) __('legacy/usercp.checkbox_pm_on_'.$option),
            ];
        }

        return new UsercpPersonalSection(
            formId: 'form'.Strings::randomCode(6),
            parked: LegacyYesNo::isYes($curUser['parked'] ?? null),
            acceptpms: UserAcceptPms::tryFrom((int) ($curUser['acceptpms'] ?? 0))?->stringValue() ?? 'yes',
            deletepms: LegacyYesNo::isYes($curUser['deletepms'] ?? null),
            savepms: LegacyYesNo::isYes($curUser['savepms'] ?? null),
            commentpm: LegacyYesNo::isYes($curUser['commentpm'] ?? null),
            notifCheckboxes: $notifCheckboxes,
            gender: UserGender::tryFrom((int) ($curUser['gender'] ?? 2))?->stringValue() ?? 'N/A',
            trackerUrlId: (string) ($curUser['tracker_url_id'] ?? ''),
            trackerUrlOptions: $trackerUrlOptions,
            country: (string) ($curUser['country'] ?? ''),
            countryOptions: $countryOptions,
            avatar: (string) ($curUser['avatar'] ?? ''),
            defaultAvatarUrl: $defaultAvatarUrl,
            bitbucketOptions: $bitbucketOptions,
            enableBitbucket: SiteConfig::current()->main->enableBitbucket(),
            info: (string) ($curUser['info'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $curUser
     */
    private function buildSecuritySection(array $curUser, string $type): UsercpSecuritySection
    {
        $showEmailChange = SiteConfig::current()->security->disableEmailChange(true)
            && SiteConfig::current()->smtp->type() !== 'none';

        // Two-step auth
        $hasSecret = ! empty($curUser['two_step_secret']);
        $secret = '';
        $qrCodeUrl = '';
        if (! $hasSecret) {
            $secret = TwoFactorAuthHelper::createSecret();
            $siteConfig = SiteConfig::current();
            $label = sprintf('%s(%s)', $siteConfig->basic->siteName(), (string) ($curUser['username'] ?? ''));
            $qrCodeUrl = TwoFactorAuthHelper::qrCodeUrl($label, $secret);
        }

        $currentPrivacy = UserPrivacy::tryFrom((int) ($curUser['privacy'] ?? 1)) ?? UserPrivacy::NORMAL;

        // For the confirm step, capture the posted values to re-render as hidden fields
        $confirmHidden = [];
        $isConfirm = $type === 'save';
        if ($isConfirm) {
            $confirmHidden = [
                'resetpasskey' => (string) (request()->post('resetpasskey') ?? ''),
                'resetauthkey' => (string) (request()->post('resetauthkey') ?? ''),
                'email' => trim((string) request()->post('email')),
                'chpassword' => (string) (request()->post('chpassword') ?? ''),
                'privacy' => (string) (request()->post('privacy') ?? ''),
                'two_step_secret' => (string) (request()->post('two_step_secret') ?? ''),
                'two_step_code' => (string) (request()->post('two_step_code') ?? ''),
            ];
        }

        // Saved message flags
        $savedFlags = [
            'mail' => request()->query('mail') === '1',
            'passkey' => request()->query('passkey') === '1',
            'password' => request()->query('password') === '1',
            'privacy' => request()->query('privacy') === '1',
        ];

        $savedMessage = '';

        if ($isConfirm) {
            AssetAppender::js('js/nx-crypto.js', 'footer', true, 'nx-crypto');
            AssetAppender::js('js/auth-form.js', 'footer', true, 'auth-form');
        } else {
            AssetAppender::js('js/auth-form.js', 'footer', true, 'auth-form');

            $savedMessage = (string) (__('legacy/usercp.text_saved'));
            if ($savedFlags['mail']) {
                $savedMessage .= ' '.(__('legacy/usercp.std_confirmation_email_sent'));
            }
            if ($savedFlags['passkey']) {
                $savedMessage .= ' '.(__('legacy/usercp.std_passkey_reset'));
            }
            if ($savedFlags['password']) {
                $savedMessage .= ' '.(__('legacy/usercp.std_password_changed'));
            }
            if ($savedFlags['privacy']) {
                $savedMessage .= ' '.(__('legacy/usercp.std_privacy_level_updated'));
            }
        }

        return new UsercpSecuritySection(
            type: $type,
            isConfirm: $isConfirm,
            confirmHidden: $confirmHidden,
            savedFlags: $savedFlags,
            savedMessage: $savedMessage,
            showEmailChange: $showEmailChange,
            twoStep: new TwoStepState($hasSecret, $secret, $qrCodeUrl),
            privacy: $currentPrivacy->stringValue(),
            email: (string) ($curUser['email'] ?? ''),
            passkeys: $isConfirm ? [] : $this->buildPasskeyItems((int) ($curUser['id'] ?? 0)),
            cspNonce: (string) request()->attributes->get('csp_nonce', ''),
            confirmHtml: SafeHtml::fromTrustedHtml(''),
        );
    }

    /**
     * Map the user's registered passkeys to view items with resolved
     * authenticator metadata (icon + display name) from the AAGUID list.
     *
     * @return list<PasskeyItem>
     */
    private function buildPasskeyItems(int $userId): array
    {
        AssetAppender::js('js/passkey.js', 'footer', true);

        $passkeys = $this->passkeyRepository->getList($userId);
        $aaguids = $passkeys->isEmpty() ? [] : (array) $this->passkeyRepository->getAaguids();

        $items = [];
        foreach ($passkeys as $passkey) {
            $meta = $aaguids[$passkey->getAaguidFormatted()] ?? null;
            $items[] = new PasskeyItem(
                credentialId: (string) $passkey->credential_id,
                iconUrl: isset($meta['icon_dark']) ? (string) $meta['icon_dark'] : UserPasskeyRepository::DEFAULT_ICON,
                iconAlt: isset($meta['name']) ? (string) $meta['name'] : (string) $passkey->credential_id,
                displayName: isset($meta['name']) ? (string) $meta['name'] : (string) $passkey->credential_id,
                showCredentialId: $meta !== null,
                createdAt: $passkey->created_at,
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
        $deleteLabel = __('legacy/functions.text_delete');
        $confirmRemoveLabel = __('legacy/functions.std_confirm_remove');

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

    /**
     * @param  array<string, mixed>  $curUser
     */
    private function buildTrackerSection(array $curUser): UsercpTrackerSection
    {
        $showTooltipSetting = SiteConfig::current()->tweak->enableTooltip();
        $browsecatmode = SiteConfig::current()->main->browseCat(1);

        $notifs = (string) ($curUser['notifs'] ?? '');
        $specialState = 0;
        for ($i = 7; $i >= 0; $i--) {
            if (str_contains($notifs, "[spstate={$i}]")) {
                $specialState = $i;
                break;
            }
        }

        $categoriesTable = $this->searchCategoryTableFactory->create($browsecatmode, 'yes', '/web/torrents?allsec=1&', '', 3, $notifs, ['section_name' => true]);

        $currentTheme = UserTheme::fromStringSafe(is_string($curUser['theme'] ?? null) ? $curUser['theme'] : null)->value;
        $themeOptions = [];
        foreach (UserTheme::cases() as $theme) {
            $themeOptions[$theme->value] = (string) __('legacy/usercp.select_theme_'.$theme->value);
        }

        $stylesheetOptions = [];
        foreach (StyleRepository::all() as $id => $row) {
            $stylesheetOptions[$id] = (string) ($row['name'] ?? $id);
        }

        $currentFolder = Locale::folderFromCookie((string) Input::cookieValue('c_lang_folder', ''), false);
        $siteLanguages = [];
        $currentLangId = 0;
        foreach (Locale::languageList('site_lang', true) as $row) {
            $siteLanguages[(int) $row['id']] = (string) $row['lang_name'];
            if ($row['site_lang_folder'] === $currentFolder) {
                $currentLangId = (int) $row['id'];
            }
        }

        $incldead = 1;
        if (preg_match('/\[incldead=(\d)\]/', $notifs, $m)) {
            $incldead = (int) $m[1];
        }
        $inclbookmarked = 0;
        if (preg_match('/\[inclbookmarked=(\d)\]/', $notifs, $m)) {
            $inclbookmarked = (int) $m[1];
        }

        return new UsercpTrackerSection(
            formId: 'form'.Strings::randomCode(6),
            showEmailNotify: SiteConfig::current()->smtp->emailNotify()
                && SiteConfig::current()->smtp->type() !== 'none',
            pmnotif: str_contains($notifs, '[pm]'),
            emailnotif: str_contains($notifs, '[email]'),
            categoriesTable: $categoriesTable,
            incldead: $incldead,
            specialState: $specialState,
            inclbookmarked: $inclbookmarked,
            promotionOptionsHtml: SafeHtml::fromTrustedHtml(Html::promotionSelection($specialState)),
            stylesheetOptions: $stylesheetOptions,
            currentStylesheet: (int) ($curUser['stylesheet'] ?? 0),
            themeOptions: $themeOptions,
            currentTheme: $currentTheme,
            fontsize: UserFontsize::tryFrom((int) ($curUser['fontsize'] ?? 1))?->stringValue() ?? 'medium',
            langOptions: $siteLanguages,
            currentLangId: $currentLangId,
            pmnum: (int) ($curUser['pmnum'] ?? 0),
            showShoutbox: SiteConfig::current()->main->showShoutbox(),
            sbnum: (int) ($curUser['sbnum'] ?? 0),
            sbrefresh: (int) ($curUser['sbrefresh'] ?? 0),
            showdescription: LegacyYesNo::isYes($curUser['showdescription'] ?? null),
            showcomment: LegacyYesNo::isYes($curUser['showcomment'] ?? null),
            timetype: UserTimeType::tryFrom((int) ($curUser['timetype'] ?? 1))?->stringValue() ?? 'timealive',
            torrentsperpage: (int) ($curUser['torrentsperpage'] ?? 0),
            tooltip: UserTooltip::tryFrom((int) ($curUser['tooltip'] ?? 2))?->stringValue() ?? 'off',
            appendsticky: LegacyYesNo::isYes($curUser['appendsticky'] ?? null),
            appendnew: LegacyYesNo::isYes($curUser['appendnew'] ?? null),
            appendpromotion: UserAppendPromotion::tryFrom((int) ($curUser['appendpromotion'] ?? 2))?->stringValue() ?? 'icon',
            appendpicked: LegacyYesNo::isYes($curUser['appendpicked'] ?? null),
            dlicon: LegacyYesNo::isYes($curUser['dlicon'] ?? null),
            bmicon: LegacyYesNo::isYes($curUser['bmicon'] ?? null),
            showcomnum: LegacyYesNo::isYes($curUser['showcomnum'] ?? null),
            showlastcom: ! LegacyYesNo::isNo($curUser['showlastcom'] ?? null),
            showTooltipSetting: $showTooltipSetting,
        );
    }
}
