<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\TrackerUrl;
use App\Models\User;
use App\Repositories\SeedBoxRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UsercpRepository;
use App\Repositories\UserPasskeyRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Form;
use App\Support\Forum;
use App\Support\Globals;
use App\Support\Hooks;
use App\Support\Html;
use App\Support\Http;
use App\Support\Input;
use App\Support\Locale;
use App\Support\Network;
use App\Support\SearchBox;
use App\Support\Strings;
use App\Support\Time;
use App\Support\TwoFactorAuthHelper;
use App\Support\Url;
use App\Support\UserDisplay;
use Nexus\Nexus;

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
    /**
     * Build the data for the requested section.
     *
     * @return array<string, mixed>
     */
    public function build(string $action, string $type): array
    {
        $curUser = (array) (app(CurrentUser::class)->get() ?? []);
        $lang = (array) (app(Globals::class)->get('lang_usercp') ?? []);
        $cache = app(LegacyRedisCache::class);
        $userInfo = UsercpRepository::getUserById((int) ($curUser['id'] ?? 0));
        $siteName = Setting::getSiteName();

        $data = [
            'lang' => $lang,
            'curUser' => $curUser,
            'userInfo' => $userInfo,
            'siteName' => $siteName,
            'action' => $action,
            'type' => $type,
            'contentWidth' => (string) (app(Globals::class)->get('CONTENT_WIDTH', '737')),
        ];

        switch ($action) {
            case 'personal':
                $data['personal'] = $this->buildPersonal($lang, $curUser);
                break;
            case 'tracker':
                $data['tracker'] = $this->buildTracker($lang, $curUser);
                break;
            case 'forum':
                $data['forum'] = $this->buildForum($lang, $curUser);
                break;
            case 'security':
                $data['security'] = $this->buildSecurity($lang, $curUser, $type);
                break;
            default:
                $data['home'] = $this->buildHome($lang, $curUser, $cache, $userInfo);
                break;
        }

        Nexus::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);

        return $data;
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
        $commentCount = UsercpRepository::getCommentCount($userId);

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
            $forumPosts = UsercpRepository::getForumPostCount($userId);
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
                $postCount = UsercpRepository::getTotalPostCount();
                if ($cache !== null) {
                    $cache->cache_value('total_posts_count', $postCount, 96400);
                }
            }
            if ($postCount > 0) {
                $percentages = round($forumPosts * 100 / $postCount, 3).'%';
            }
        }

        // IP location
        $enableLocationTweak = (string) app(Globals::class)->get('enablelocation_tweak', '') === 'yes';
        $seedBoxIcon = (new SeedBoxRepository)->renderIcon((string) ($curUser['ip'] ?? ''), $userId);
        $ipLocation = '';
        if ($enableLocationTweak) {
            [$locPub, $locMod] = Network::ipLocationWithContext((string) ($curUser['ip'] ?? ''));
            $ipLocation = Strings::hidden((string) ($curUser['ip'] ?? '')." <span title='".$locMod."'>[".$locPub.']</span>'.$seedBoxIcon);
        } else {
            $ipLocation = Strings::hidden((string) ($curUser['ip'] ?? '').$seedBoxIcon);
        }

        // Passkey login form (if passkey login enabled and deadline in future)
        $passkeyLoginForm = '';
        $siteConfig = SiteConfig::current();
        $loginSecretDeadline = $siteConfig->security->loginSecretDeadline();
        if ($siteConfig->security->loginType() === 'passkey'
            && $loginSecretDeadline !== null
            && $loginSecretDeadline > date('Y-m-d H:i:s')
        ) {
            $passkeyLoginForm = sprintf(
                '<form method="POST" action="%s/%s" style="display:inline"><input type="hidden" name="passkey" value="%s"><button type="submit" class="btn" style="font-size:inherit;padding:0 4px">%s</button></form>',
                Url::schemeAndHost(false),
                $siteConfig->security->loginSecret(),
                htmlspecialchars((string) ($curUser['passkey'] ?? ''), ENT_QUOTES),
                $lang['text_passkey_login'] ?? 'Login'
            );
        }

        // Seed box
        $seedBox = $this->buildSeedBox($lang, $userId);

        // Tokens
        $tokens = $this->buildTokens($lang, $userInfo);

        // Recently read topics
        $readTopics = $this->buildReadTopics($lang, $userId, $cache);

        return [
            'commentCount' => $commentCount,
            'joinDate' => $joinDate,
            'forumPosts' => $forumPosts,
            'dayPosts' => $dayPosts,
            'percentages' => $percentages,
            'ipLocation' => $ipLocation,
            'passkeyLoginForm' => $passkeyLoginForm,
            'seedBox' => $seedBox,
            'tokens' => $tokens,
            'readTopics' => $readTopics,
            'showAvatar' => ! empty($curUser['avatar']),
            'avatarUrl' => (string) ($curUser['avatar'] ?? ''),
            'passkey' => Strings::hidden((string) ($curUser['passkey'] ?? '')),
            'email' => (string) ($curUser['email'] ?? ''),
            'invites' => (int) ($curUser['invites'] ?? 0),
            'seedbonus' => (string) ($curUser['seedbonus'] ?? '0'),
        ];
    }

    /**
     * Build seed box section data.
     *
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildSeedBox(array $lang, int $userId): array
    {
        $siteConfig = SiteConfig::current();
        if (! $siteConfig->seedBox->enabled()) {
            return ['enabled' => false];
        }

        $langFunctions = (array) (app(Globals::class)->get('lang_functions') ?? []);

        $columns = [
            'operator' => Locale::trans('label.seed_box_record.operator', [], null),
            'bandwidth' => Locale::trans('label.seed_box_record.bandwidth', [], null),
            'ip' => Locale::trans('label.seed_box_record.ip', [], null),
            'comment' => Locale::trans('label.comment', [], null),
            'status' => Locale::trans('label.seed_box_record.status', [], null),
        ];

        $records = UsercpRepository::getSeedBoxRecords($userId);

        return [
            'enabled' => true,
            'columns' => $columns,
            'records' => $records,
            'addLabel' => $lang['add_seed_box_btn'] ?? 'Add',
            'deleteLabel' => $langFunctions['text_delete'] ?? 'Delete',
            'confirmRemoveLabel' => $langFunctions['std_confirm_remove'] ?? 'Confirm remove?',
            'rowLabel' => $lang['row_seed_box'] ?? 'Seed box',
        ];
    }

    /**
     * Build tokens section data.
     *
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildTokens(array $lang, User $userInfo): array
    {
        $langFunctions = (array) (app(Globals::class)->get('lang_functions') ?? []);

        $permissions = TokenRepository::listUserTokenPermissionAllowed();
        $permissionOptions = [];
        foreach ($permissions as $name => $label) {
            $permissionOptions[] = sprintf('<label><input type="checkbox" name="permissions[]" value="%s">%s</label>', $name, $label);
        }

        $tokens = UsercpRepository::getUserTokens($userInfo);

        return [
            'label' => Locale::trans('token.label', [], null),
            'columnName' => Locale::trans('label.name', [], null),
            'columnPermission' => Locale::trans('token.permission', [], null),
            'columnCreatedAt' => Locale::trans('label.created_at', [], null),
            'actionLabel' => Locale::trans('label.action', [], null),
            'actionCreate' => Locale::trans('label.create', [], null),
            'permissionCheckbox' => implode('', $permissionOptions),
            'tokens' => $tokens,
            'deleteLabel' => $langFunctions['text_delete'] ?? 'Delete',
            'confirmRemoveLabel' => $langFunctions['std_confirm_remove'] ?? 'Confirm remove?',
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
        $topicRows = UsercpRepository::getReadTopics($userId);
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
                $posts = UsercpRepository::getTopicPostCount($topicId);
                if ($cache !== null) {
                    $cache->cache_value('topic_'.$topicId.'_post_count', $posts, 3600);
                }
            }
            $replies = max(0, $posts - 1);

            $arr = Forum::postRowWithContext((int) $topicArr['lastpost']);
            $postid = (int) ($arr['id'] ?? 0);
            $userid = (int) ($arr['userid'] ?? 0);
            $added = Time::format((string) ($arr['added'] ?? ''), true, false);

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

    /**
     * Build the personal settings form data.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildPersonal(array $lang, array $curUser): array
    {
        // Countries
        $countryOptions = '';
        $countryRows = UsercpRepository::getCountryOptions();
        foreach ($countryRows as $ct) {
            $countryOptions .= '<option value='.htmlspecialchars((string) $ct->id).''
                .(htmlspecialchars((string) ($curUser['country'] ?? '')) === htmlspecialchars((string) $ct->id) ? ' selected' : '')
                .'>'.htmlspecialchars((string) $ct->name)."</option>\n";
        }

        // Tracker URLs
        $trackerUrlOptions = '';
        $trackerUrlList = TrackerUrl::listAll();
        foreach ($trackerUrlList as $item) {
            $trackerUrlOptions .= '<option value='.htmlspecialchars((string) $item->id).''
                .(htmlspecialchars((string) ($curUser['tracker_url_id'] ?? '')) === htmlspecialchars((string) $item->id) ? ' selected' : '')
                .'>'.htmlspecialchars((string) $item->url)."</option>\n";
        }

        // Bitbucket avatars
        $bitbucketRows = UsercpRepository::getBitbucketOptions();
        $bitbucketOptions = '';
        $baseUrl = (string) app(Globals::class)->get('BASEURL', '');
        foreach ($bitbucketRows as $sor) {
            $bitbucketOptions .= '<option value="'.Http::protocolPrefix(Url::isSecure()).$baseUrl.'/bitbucket/'.htmlspecialchars((string) $sor->name).'">'.htmlspecialchars((string) $sor->name).'</option>';
        }

        // Notification options
        $notificationOptions = User::$notificationOptions;

        return [
            'countryOptions' => $countryOptions,
            'trackerUrlOptions' => $trackerUrlOptions,
            'bitbucketOptions' => $bitbucketOptions,
            'notificationOptions' => $notificationOptions,
            'enableBitbucket' => (string) app(Globals::class)->get('enablebitbucket_main', '') === 'yes',
            'baseUrl' => $baseUrl,
            'selectNoneLabel' => $lang['select_none_selected'] ?? 'None',
            'selectChooseAvatar' => $lang['select_choose_avatar'] ?? 'Choose avatar',
            'selectNothing' => $lang['select_nothing'] ?? 'Nothing',
            'defaultAvatarUrl' => Http::protocolPrefix(Url::isSecure()).$baseUrl.'/pic/default_avatar.png',
        ];
    }

    /**
     * Build the tracker/browse settings form data.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildTracker(array $lang, array $curUser): array
    {
        $showTooltipSetting = (string) app(Globals::class)->get('enabletooltip_tweak', '') === 'yes';
        $browsecatmode = (int) app(Globals::class)->get('browsecatmode', 1);

        // Special state from notifs
        $notifs = (string) ($curUser['notifs'] ?? '');
        $specialState = 0;
        for ($i = 7; $i >= 0; $i--) {
            if (str_contains($notifs, "[spstate={$i}]")) {
                $specialState = $i;
                break;
            }
        }

        // Categories table
        $categories = SearchBox::buildCategoryTableWithContext($browsecatmode, 'yes', 'torrents.php?allsec=1', '', 3, $notifs, ['section_name' => true]);

        // Stylesheets
        $ssSa = UsercpRepository::getStylesheetOptions();
        ksort($ssSa);
        $stylesheetOptions = '';
        foreach ($ssSa as $ssName => $ssId) {
            $selected = ((int) $ssId === (int) ($curUser['stylesheet'] ?? 0)) ? ' selected' : '';
            $stylesheetOptions .= "<option value={$ssId}{$selected}>{$ssName}</option>\n";
        }

        // Site languages
        $siteLangs = Locale::languageList('site_lang', true);
        $currentFolder = Locale::folderFromCookie((string) Input::cookieValue('c_lang_folder', ''), false);
        $langOptions = '';
        foreach ($siteLangs as $row) {
            $se = ($row['site_lang_folder'] === $currentFolder) ? ' selected' : '';
            $langOptions .= '<option value='.(int) $row['id'].$se.'>'.htmlspecialchars((string) $row['lang_name'])."</option>\n";
        }

        // Email notification row visibility
        $showEmailNotify = (string) app(Globals::class)->get('emailnotify_smtp', '') === 'yes'
            && (string) app(Globals::class)->get('smtptype', '') !== 'none';
        $showShoutbox = (string) app(Globals::class)->get('showshoutbox_main', '') === 'yes';

        return [
            'showTooltipSetting' => $showTooltipSetting,
            'specialState' => $specialState,
            'categories' => $categories,
            'delimiter' => '<div style="height: 1px;background-color: #eee;margin: 10px 0"></div>',
            'stylesheetOptions' => $stylesheetOptions,
            'langOptions' => $langOptions,
            'showEmailNotify' => $showEmailNotify,
            'showShoutbox' => $showShoutbox,
            'promotionSelection' => Html::promotionSelection($specialState),
        ];
    }

    /**
     * Build the forum settings form data.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildForum(array $lang, array $curUser): array
    {
        return [
            'showTooltipSetting' => (string) app(Globals::class)->get('enabletooltip_tweak', '') === 'yes',
        ];
    }

    /**
     * Build the security settings form data.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildSecurity(array $lang, array $curUser, string $type): array
    {
        $showEmailChange = (string) app(Globals::class)->get('disableemailchange', '') !== 'no'
            && (string) app(Globals::class)->get('smtptype', '') !== 'none';

        // Two-step auth
        $twoStep = [
            'hasSecret' => ! empty($curUser['two_step_secret']),
            'secret' => '',
            'qrCodeUrl' => '',
        ];
        if (! $twoStep['hasSecret']) {
            $secret = TwoFactorAuthHelper::createSecret();
            $siteConfig = SiteConfig::current();
            $label = sprintf('%s(%s)', $siteConfig->basic->siteName(), (string) ($curUser['username'] ?? ''));
            $twoStep['secret'] = $secret;
            $twoStep['qrCodeUrl'] = TwoFactorAuthHelper::qrCodeUrl($label, $secret);
        }

        // Privacy radios
        $privacyRadios = [
            'normal' => $this->privacyRadio('normal', $lang['radio_normal'] ?? 'normal', (string) ($curUser['privacy'] ?? '')),
            'low' => $this->privacyRadio('low', $lang['radio_low'] ?? 'low', (string) ($curUser['privacy'] ?? '')),
            'strong' => $this->privacyRadio('strong', $lang['radio_strong'] ?? 'strong', (string) ($curUser['privacy'] ?? '')),
        ];

        // For the confirm step, capture the posted values to re-render as hidden fields
        $confirmHidden = [];
        $isConfirm = $type === 'save';
        if ($isConfirm) {
            $confirmHidden = [
                'resetpasskey' => (string) (request()->post('resetpasskey') ?? ''),
                'resetauthkey' => (string) (request()->post('resetauthkey') ?? ''),
                'email' => htmlspecialchars(trim((string) request()->post('email'))),
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

        return [
            'type' => $type,
            'isConfirm' => $isConfirm,
            'confirmHidden' => $confirmHidden,
            'savedFlags' => $savedFlags,
            'showEmailChange' => $showEmailChange,
            'twoStep' => $twoStep,
            'privacyRadios' => $privacyRadios,
            'passkeyListHtml' => $this->capturePasskeyList((int) ($curUser['id'] ?? 0)),
            'confirmHtml' => $isConfirm ? $this->captureConfirmExtras() : '',
        ];
    }

    /**
     * Render a privacy radio input.
     */
    private function privacyRadio(string $name, string $descr, string $current): string
    {
        $checked = $current === $name ? ' checked="checked"' : '';

        return '<input type="radio" name="privacy" value="'.htmlspecialchars($name).'"'.$checked.' /> '.htmlspecialchars($descr);
    }

    /**
     * Capture the passkey list HTML (UserPasskeyRepository::renderList echoes).
     */
    private function capturePasskeyList(int $userId): string
    {
        ob_start();
        UserPasskeyRepository::renderList($userId);

        return (string) ob_get_clean();
    }

    /**
     * Capture any extra HTML emitted by the usercp_security_setting_form hook.
     */
    private function captureConfirmExtras(): string
    {
        ob_start();
        Hooks::doAction('usercp_security_update_confirm', request()->post());

        return (string) ob_get_clean();
    }
}
