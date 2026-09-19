<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserPrivacy;
use App\Enums\UserTheme;
use App\Models\Setting;
use App\Models\TrackerUrl;
use App\Models\User;
use App\Repositories\TokenRepository;
use App\Repositories\UsercpLookupRepository;
use App\Repositories\UsercpRepository;
use App\Repositories\UserPasskeyRepository;
use App\Support\AssetAppender;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Forum;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Html\SafeHtml;
use App\Support\Http;
use App\Support\Input;
use App\Support\LegacyYesNo;
use App\Support\Locale;
use App\Support\Network;
use App\Support\SearchBox;
use App\Support\Strings;
use App\Support\Time;
use App\Support\TwoFactorAuthHelper;
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
        private readonly UserPasskeyRepository $passkeyRepository,
        private readonly TokenRepository $tokenRepository,
    ) {}

    /**
     * Build the data for the requested section.
     */
    public function build(string $action, string $type): UsercpPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $cache = $this->cache;
        $userInfo = $this->usercpRepository->getUserById((int) ($curUser['id'] ?? 0));
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
     * @return array<string, mixed>
     */
    private function buildHome(array $curUser, ?LegacyRedisCache $cache, User $userInfo): array
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
                '<form method="POST" action="%s/%s"><input type="hidden" name="passkey" value="%s"><input type="hidden" name="timestamp" value="%d"><input type="hidden" name="signature" value="%s"><button type="submit" class="btn">%s</button></form>',
                Url::schemeAndHost(false),
                $siteConfig->security->loginSecret(),
                htmlspecialchars($passkey, ENT_QUOTES),
                $timestamp,
                htmlspecialchars($signature, ENT_QUOTES),
                ('Login')
            );
        }

        // Tokens
        $tokens = $this->buildTokenSection($userInfo);

        // Recently read topics
        $readTopics = $this->buildReadTopics($userId, $cache);

        $avatarHtml = '';
        if (! empty($curUser['avatar'])) {
            $avatarHtml = '<img src="'.htmlspecialchars((string) $curUser['avatar']).'" border=0>';
        }

        $invitesHtml = ((int) ($curUser['invites'] ?? 0)).' [<a href="invite.php?id='.$userId.'" title="'.(__('legacy/usercp.link_send_invitation')).'">'.htmlspecialchars(__('legacy/usercp.text_send')).'</a>]';
        $karmaHtml = ((string) ($curUser['seedbonus'] ?? '0')).' [<a href="mybonus.php" title="'.(__('legacy/usercp.link_use_karma_points')).'">'.htmlspecialchars(__('legacy/usercp.text_use')).'</a>]';
        $commentsHtml = $commentCount.' [<a href="userhistory.php?action=viewcomments&id='.$userId.'" title="'.(__('legacy/usercp.link_view_comments')).'">'.htmlspecialchars(__('legacy/usercp.text_view')).'</a>]';

        $forumPostsHtml = null;
        if ($forumPosts > 0) {
            $forumPostsHtml = $forumPosts.' [<a href="userhistory.php?action=viewposts&id='.$userId.'" title="'.(__('legacy/usercp.link_view_posts')).'">'.htmlspecialchars(__('legacy/usercp.text_view')).'</a>] ('.$dayPosts.htmlspecialchars(__('legacy/usercp.text_posts_per_day')).'; '.$percentages.htmlspecialchars(__('legacy/usercp.text_of_total_posts')).')';
        }

        return [
            'commentCount' => $commentCount,
            'joinDate' => SafeHtml::fromTrustedHtml($joinDate),
            'forumPosts' => $forumPosts,
            'dayPosts' => $dayPosts,
            'percentages' => $percentages,
            'ipLocation' => SafeHtml::fromTrustedHtml($ipLocation),
            'passkeyLoginForm' => SafeHtml::fromTrustedHtml($passkeyLoginForm),
            'tokens' => $tokens,
            'readTopics' => $readTopics,
            'showAvatar' => ! empty($curUser['avatar']),
            'avatarUrl' => (string) ($curUser['avatar'] ?? ''),
            'passkey' => Strings::hidden((string) ($curUser['passkey'] ?? '')),
            'email' => (string) ($curUser['email'] ?? ''),
            'invites' => (int) ($curUser['invites'] ?? 0),
            'seedbonus' => (string) ($curUser['seedbonus'] ?? '0'),
            'avatarHtml' => SafeHtml::fromTrustedHtml($avatarHtml),
            'invitesHtml' => SafeHtml::fromTrustedHtml($invitesHtml),
            'karmaHtml' => SafeHtml::fromTrustedHtml($karmaHtml),
            'commentsHtml' => SafeHtml::fromTrustedHtml($commentsHtml),
            'forumPostsHtml' => SafeHtml::fromTrustedHtml((string) ($forumPostsHtml ?? '')),
        ];
    }

    /**
     * Build recently read topics section data.
     *
     * @return array<string, mixed>
     */
    private function buildReadTopics(int $userId, ?LegacyRedisCache $cache): array
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
                'lastPostAdded' => SafeHtml::fromTrustedHtml($added),
            ];
        }

        return [
            'items' => $items,
            'colTopicTitle' => __('legacy/usercp.col_topic_title'),
            'colReplies' => __('legacy/usercp.col_replies'),
            'colViews' => __('legacy/usercp.col_views'),
            'colTopicStarter' => __('legacy/usercp.col_topic_starter'),
            'colLastPost' => __('legacy/usercp.col_last_post'),
            'title' => __('legacy/usercp.text_recently_read_topics'),
        ];
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildForumSection(array $curUser): array
    {
        $showTooltipSetting = (string) $this->globals->get('enabletooltip_tweak', '') === 'yes';

        $rowsHtml = '';
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_topics_per_page'), '<input type=text size=10 name=topicsperpage value='.(int) ($curUser['topicsperpage'] ?? 0).'>'.htmlspecialchars(__('legacy/usercp.text_zero_equals_default')), 1, '', true);
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_posts_per_page'), '<input type=text size=10 name=postsperpage value='.(int) ($curUser['postsperpage'] ?? 0).'> '.htmlspecialchars(__('legacy/usercp.text_zero_equals_default')), 1, '', true);
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_view_avatars'), '<input type=checkbox name=avatars'.(LegacyYesNo::isYes($curUser['avatars'] ?? null) ? ' checked' : '').'>'.htmlspecialchars(__('legacy/usercp.checkbox_low_bandwidth_note')), 1, '', true);
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_view_signatures'), '<input type=checkbox name=signatures'.(LegacyYesNo::isYes($curUser['signatures'] ?? null) ? ' checked' : '').'>'.htmlspecialchars(__('legacy/usercp.checkbox_low_bandwidth_note')), 1, '', true);
        if ($showTooltipSetting) {
            $rowsHtml .= (string) Html::frow(__('legacy/usercp.row_tooltip_last_post'), '<input type=checkbox name=ttlastpost'.(LegacyYesNo::isYes($curUser['showlastpost'] ?? null) ? ' checked' : '').'>'.htmlspecialchars(__('legacy/usercp.checkbox_last_post_note')), 1, '', true);
        }
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_click_on_topic'), '<input type=radio name=clicktopic'.(($curUser['clicktopic'] ?? '') === 'firstpage' ? ' checked' : '').' value="firstpage">'.htmlspecialchars(__('legacy/usercp.text_go_to_first_page')).'<input type=radio name=clicktopic'.(($curUser['clicktopic'] ?? '') === 'lastpage' ? ' checked' : '').' value="lastpage">'.htmlspecialchars(__('legacy/usercp.text_go_to_last_page')), 1, '', true);
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_forum_signature'), '<textarea name=signature rows=10>'.htmlspecialchars((string) ($curUser['signature'] ?? '')).'</textarea><br />'.(__('legacy/usercp.text_signature_note')), 1, '', true);

        return [
            'formId' => 'form'.Strings::randomCode(6),
            'rowsHtml' => SafeHtml::fromTrustedHtml($rowsHtml),
            'showTooltipSetting' => $showTooltipSetting,
        ];
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildPersonalSection(array $curUser): array
    {
        $countryOptions = '';
        $countryRows = $this->usercpLookupRepository->getCountryOptions();
        foreach ($countryRows as $ct) {
            $countryOptions .= '<option value='.htmlspecialchars((string) $ct->id).''
                .(htmlspecialchars((string) ($curUser['country'] ?? '')) === htmlspecialchars((string) $ct->id) ? ' selected' : '')
                .'>'.htmlspecialchars((string) $ct->name)."</option>\n";
        }

        $trackerUrlOptions = '';
        $trackerUrlList = TrackerUrl::listAll();
        foreach ($trackerUrlList as $item) {
            $trackerUrlOptions .= '<option value='.htmlspecialchars((string) $item->id).''
                .(htmlspecialchars((string) ($curUser['tracker_url_id'] ?? '')) === htmlspecialchars((string) $item->id) ? ' selected' : '')
                .'>'.htmlspecialchars((string) $item->url)."</option>\n";
        }

        $bitbucketRows = $this->usercpLookupRepository->getBitbucketOptions();
        $bitbucketOptions = '';
        $baseUrl = (string) $this->globals->get('BASEURL', '');
        foreach ($bitbucketRows as $sor) {
            $bitbucketOptions .= '<option value="'.Http::protocolPrefix(Url::isSecure()).$baseUrl.'/bitbucket/'.htmlspecialchars((string) $sor->name).'">'.htmlspecialchars((string) $sor->name).'</option>';
        }

        $enableBitbucket = (string) $this->globals->get('enablebitbucket_main', '') === 'yes';
        $selectNoneLabel = __('legacy/usercp.select_none_selected');
        $selectChooseAvatar = __('legacy/usercp.select_choose_avatar');
        $selectNothing = __('legacy/usercp.select_nothing');
        $defaultAvatarUrl = Http::protocolPrefix(Url::isSecure()).$baseUrl.'/pic/default_avatar.png';

        $rowsHtml = '';
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_account_parked'), '<input type=checkbox name=parked'.(LegacyYesNo::isYes($curUser['parked'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.checkbox_pack_my_account')).'<br /><span class="small nx-size-1">'.(__('legacy/usercp.text_account_pack_note')).'</span>', 1, '', true);

        $pmY = (__('legacy/usercp.text_accept_pms')).'<input type=radio name=acceptpms'.(($curUser['acceptpms'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.radio_all_except_blocks')).'<input type=radio name=acceptpms'.(($curUser['acceptpms'] ?? '') === 'friends' ? ' checked' : '').' value=friends>'.htmlspecialchars(__('legacy/usercp.radio_friends_only')).'<input type=radio name=acceptpms'.(($curUser['acceptpms'] ?? '') === 'no' ? ' checked' : '').' value=no>'.htmlspecialchars(__('legacy/usercp.radio_staff_only'))
            .'<br /><input type=checkbox name=deletepms'.(LegacyYesNo::isYes($curUser['deletepms'] ?? null) ? ' checked' : '').'> '.(__('legacy/usercp.checkbox_delete_pms'))
            .'<br /><input type=checkbox name=savepms'.(LegacyYesNo::isYes($curUser['savepms'] ?? null) ? ' checked' : '').'> '.(__('legacy/usercp.checkbox_save_pms'))
            .'<br /><input type=checkbox name=commentpm'.(LegacyYesNo::isYes($curUser['commentpm'] ?? null) ? ' checked' : '').' value=yes> '.(__('legacy/usercp.checkbox_pm_on_comments'));
        $notifs = (string) ($curUser['notifs'] ?? '');
        foreach (User::$notificationOptions as $option) {
            $pmY .= sprintf('<br /><input type="checkbox" name="notifs[%s]"%s value="yes" /> %s', $option, (is_null($curUser['notifs'] ?? null) || str_contains($notifs, "[{$option}]") ? ' checked' : ''), htmlspecialchars((string) __('legacy/usercp.checkbox_pm_on_'.$option)));
        }
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_pms'), $pmY, 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_gender'), '<input type=radio name=gender'.(($curUser['gender'] ?? '') === 'N/A' ? ' checked' : '').' value=N/A>'.htmlspecialchars(__('legacy/usercp.radio_not_available')).'
<input type=radio name=gender'.(($curUser['gender'] ?? '') === 'Male' ? ' checked' : '').' value=Male>'.htmlspecialchars(__('legacy/usercp.radio_male')).'<input type=radio name=gender'.(($curUser['gender'] ?? '') === 'Female' ? ' checked' : '').' value=Female>'.htmlspecialchars(__('legacy/usercp.radio_female')), 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_tracker_url'), "<select name=tracker_url_id>\n".$trackerUrlOptions."\n</select>".'<br /><span class="small nx-size-1">'.(__('legacy/usercp.row_tracker_url_help')).'</span>', 1, '', true);
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_country'), "<select name=country>\n".'<option value=0>---- '.htmlspecialchars($selectNoneLabel)." ----</option>\n".$countryOptions."\n</select>", 1, '', true);

        $avatarCell = '<img src='.($curUser['avatar'] ?? '' ? "'".htmlspecialchars((string) $curUser['avatar'])."'" : "'".htmlspecialchars($defaultAvatarUrl)."'")." name='avatarimg'><br />
  <select name=savatar >
  <option value='".htmlspecialchars((string) ($curUser['avatar'] ?? ''))."'>".htmlspecialchars($selectChooseAvatar)."</option>
  <option value='".htmlspecialchars($defaultAvatarUrl)."'>".htmlspecialchars($selectNothing).'</option>
  '.$bitbucketOptions.'
  </select><input type=text name=avatar value="'.htmlspecialchars((string) ($curUser['avatar'] ?? '')).
  "\"><br />\n".htmlspecialchars(__('legacy/usercp.text_avatar_note')).($enableBitbucket ? (__('legacy/usercp.text_bitbucket_note')) : '');
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_avatar_url'), $avatarCell, 1, '', true);

        $rowsHtml .= (string) Html::frow(__('legacy/usercp.row_info'), '<textarea name="info" rows="10" >'.htmlspecialchars((string) ($curUser['info'] ?? '')).'</textarea><br />'.(__('legacy/usercp.text_info_note')), 1, '', true);

        return [
            'formId' => 'form'.Strings::randomCode(6),
            'rowsHtml' => SafeHtml::fromTrustedHtml($rowsHtml),
            'enableBitbucket' => $enableBitbucket,
        ];
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildSecuritySection(array $curUser, string $type): array
    {
        $showEmailChange = (string) $this->globals->get('disableemailchange', '') !== 'no'
            && (string) $this->globals->get('smtptype', '') !== 'none';

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
        $currentPrivacy = UserPrivacy::tryFrom((int) ($curUser['privacy'] ?? 1)) ?? UserPrivacy::NORMAL;
        $privacyRadios = [
            'normal' => $this->privacyRadio('normal', __('legacy/usercp.radio_normal'), $currentPrivacy->stringValue()),
            'low' => $this->privacyRadio('low', __('legacy/usercp.radio_low'), $currentPrivacy->stringValue()),
            'strong' => $this->privacyRadio('strong', __('legacy/usercp.radio_strong'), $currentPrivacy->stringValue()),
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

        $savedMessage = '';
        $rowsHtml = '';
        $passkeyListHtml = '';

        if ($isConfirm) {
            AssetAppender::js('js/crypto-js.js', 'footer', true, 'crypto-js');
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

            $rowsHtml .= (string) Html::frowSmall(
                __('legacy/usercp.row_reset_passkey'),
                '<input type=checkbox name=resetpasskey value=1 />'.htmlspecialchars(__('legacy/usercp.checkbox_reset_my_passkey')).'<br /><span class="small">'.(__('legacy/usercp.text_reset_passkey_note')).'</span>',
                1,
                '',
                true
            );

            if ($twoStep['hasSecret']) {
                $twoStepCell = '<input type=text name=two_step_code />'.htmlspecialchars(__('legacy/usercp.text_two_step_secret_unbind_note'));
            } else {
                $cspNonce = (string) request()->attributes->get('csp_nonce', '');
                $twoStepCell = sprintf('<style nonce="%s">.tfa-row{display:flex;align-items:center}.tfa-row>div+div{padding-left:20px}</style><div class="tfa-row">', htmlspecialchars($cspNonce));
                $twoStepCell .= sprintf('<div><img src="%s" /></div>', htmlspecialchars($twoStep['qrCodeUrl']));
                $twoStepCell .= sprintf(
                    '<div>%s<a href="%s" target="_blank">Link</a><br /><br />%s%s<br/><br/>%s<input type=hidden name=two_step_secret value="%s" /><input type=text name=two_step_code readonly /></div>',
                    (__('legacy/usercp.text_two_step_secret_bind_by_qrdoe_note')),
                    htmlspecialchars($twoStep['qrCodeUrl']),
                    htmlspecialchars(__('legacy/usercp.text_two_step_secret_bind_manually_note')),
                    htmlspecialchars($twoStep['secret']),
                    htmlspecialchars(__('legacy/usercp.text_two_step_secret_bind_complete_note')),
                    htmlspecialchars($twoStep['secret'])
                );
                $twoStepCell .= sprintf('</div><script nonce="%s">document.addEventListener("focusin",function(e){if(e.target&&e.target.name==="two_step_code"){e.target.removeAttribute("readonly")}})</script>', htmlspecialchars($cspNonce));
            }
            $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_two_step_secret'), $twoStepCell, 1, '', true);

            $passkeyListHtml = $this->capturePasskeyList((int) ($curUser['id'] ?? 0));
            $rowsHtml .= '<tr><td class="rowhead" valign="top" align="right">'.htmlspecialchars(Locale::trans('passkey.passkey', [], null)).'</td><td class="rowfollow" valign="top" align="left">'.$passkeyListHtml.'</td></tr>';

            if ($showEmailChange) {
                $rowsHtml .= (string) Html::frowSmall(
                    __('legacy/usercp.row_email_address'),
                    '<input type="text" name="email" value="'.htmlspecialchars((string) ($curUser['email'] ?? '')).'" /> <br /><span class="small">'.(__('legacy/usercp.text_email_address_note')).'</span>',
                    1,
                    '',
                    true
                );
            }

            $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_change_password'), '<input type="password" class="password" />', 1, '', true);
            $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_type_password_again'), '<input type="password" class="passagain" />', 1, '', true);
            $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_privacy_level'), $privacyRadios['normal'].' '.$privacyRadios['low'].' '.$privacyRadios['strong'], 1, '', true);
        }

        return [
            'type' => $type,
            'isConfirm' => $isConfirm,
            'confirmHidden' => $confirmHidden,
            'savedFlags' => $savedFlags,
            'savedMessage' => $savedMessage,
            'rowsHtml' => SafeHtml::fromTrustedHtml($rowsHtml),
            'showEmailChange' => $showEmailChange,
            'twoStep' => $twoStep,
            'privacyRadios' => $privacyRadios,
            'passkeyListHtml' => $passkeyListHtml,
            'confirmHtml' => SafeHtml::fromTrustedHtml($isConfirm ? $this->captureConfirmExtras() : ''),
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
        $this->passkeyRepository->renderList($userId);

        return (string) ob_get_clean();
    }

    /**
     * Capture any extra HTML emitted by the usercp_security_setting_form hook.
     */
    private function captureConfirmExtras(): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTokenSection(User $userInfo): array
    {

        $permissions = $this->tokenRepository->listUserTokenPermissionAllowed();
        $permissionOptions = [];
        foreach ($permissions as $name => $permLabel) {
            $permissionOptions[] = sprintf('<label><input type="checkbox" name="permissions[]" value="%s">%s</label>', $name, $permLabel);
        }

        $tokens = $this->usercpRepository->getUserTokens($userInfo);

        $label = Locale::trans('token.label', [], null);
        $columnName = Locale::trans('label.name', [], null);
        $columnPermission = Locale::trans('token.permission', [], null);
        $columnCreatedAt = Locale::trans('label.created_at', [], null);
        $actionLabel = Locale::trans('label.action', [], null);
        $actionCreate = Locale::trans('label.create', [], null);
        $deleteLabel = __('legacy/functions.text_delete');
        $confirmRemoveLabel = __('legacy/functions.std_confirm_remove');

        $tableHtml = '';
        if (! empty($tokens)) {
            $tableHtml .= "<table border='1' cellspacing='0' cellpadding='5' id='token-table'><tr><td class='colhead'>ID</td><td class='colhead'>{$columnName}</td><td class='colhead'>{$columnPermission}</td><td class='colhead'>{$columnCreatedAt}</td><td class='colhead'>{$actionLabel}</td></tr>";
            foreach ($tokens as $tokenRecord) {
                $tableHtml .= '<tr>';
                $tableHtml .= sprintf('<td>%s</td>', (int) $tokenRecord['id']);
                $tableHtml .= sprintf('<td>%s</td>', htmlspecialchars((string) $tokenRecord['name']));
                $tableHtml .= sprintf('<td>%s</td>', htmlspecialchars((string) $tokenRecord['abilitiesText']));
                $tableHtml .= sprintf('<td>%s</td>', htmlspecialchars((string) $tokenRecord['created_at']));
                $tableHtml .= sprintf('<td><img class="staff_delete token-del" src="pic/trans.gif" alt="D" title="%s" data-id="%s"></td>', htmlspecialchars($deleteLabel), (int) $tokenRecord['id']);
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</table>';
        }
        $tableHtml .= sprintf('<div><input type="button" id="add-token-box-btn" value="%s"/></div>', htmlspecialchars($actionCreate));

        $permissionCheckbox = implode('', $permissionOptions);
        $tokenForm = <<<FORM
<div class="form-box">
<form id="token-box-form">
    <div class="form-control-row">
        <div class="label">{$columnName}</div>
        <div class="field"><input type="text" name="name"></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$columnPermission}</div>
        <div class="field">{$permissionCheckbox}</div>
    </div>
</form>
</div>
FORM;

        $tokLabel = addslashes($label);
        $tokCreate = addslashes($actionCreate);
        $tokConfirmRemove = addslashes($confirmRemoveLabel);
        $tokenJs = <<<JS
document.getElementById('add-token-box-btn').addEventListener('click', function () {
    layer.open({
        type: 1,
        title: "{$tokLabel} {$tokCreate}",
        content: `{$tokenForm}`,
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

        return [
            'label' => $label,
            'columnName' => $columnName,
            'columnPermission' => $columnPermission,
            'columnCreatedAt' => $columnCreatedAt,
            'actionLabel' => $actionLabel,
            'actionCreate' => $actionCreate,
            'permissionCheckbox' => $permissionCheckbox,
            'tokens' => $tokens,
            'deleteLabel' => $deleteLabel,
            'confirmRemoveLabel' => $confirmRemoveLabel,
            'tableHtml' => SafeHtml::fromTrustedHtml($tableHtml),
        ];
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildTrackerSection(array $curUser): array
    {
        $showTooltipSetting = (string) $this->globals->get('enabletooltip_tweak', '') === 'yes';
        $browsecatmode = (int) $this->globals->get('browsecatmode', 1);

        $notifs = (string) ($curUser['notifs'] ?? '');
        $specialState = 0;
        for ($i = 7; $i >= 0; $i--) {
            if (str_contains($notifs, "[spstate={$i}]")) {
                $specialState = $i;
                break;
            }
        }

        $categories = SearchBox::buildCategoryTableWithContext($browsecatmode, 'yes', 'torrents.php?allsec=1', '', 3, $notifs, ['section_name' => true]);

        $currentTheme = UserTheme::fromStringSafe(is_string($curUser['theme'] ?? null) ? $curUser['theme'] : null)->value;
        $themeOptions = '';
        foreach (UserTheme::cases() as $theme) {
            $selected = $theme->value === $currentTheme ? ' selected' : '';
            $themeOptions .= '<option value="'.$theme->value.'"'.$selected.'>'.htmlspecialchars(__('legacy/usercp.select_theme_'.$theme->value))."</option>\n";
        }

        $siteLangs = Locale::languageList('site_lang', true);
        $currentFolder = Locale::folderFromCookie((string) Input::cookieValue('c_lang_folder', ''), false);
        $langOptions = '';
        foreach ($siteLangs as $row) {
            $se = ($row['site_lang_folder'] === $currentFolder) ? ' selected' : '';
            $langOptions .= '<option value='.(int) $row['id'].$se.'>'.htmlspecialchars((string) $row['lang_name'])."</option>\n";
        }

        $rowsHtml = '';
        if ((string) $this->globals->get('emailnotify_smtp', '') === 'yes'
            && (string) $this->globals->get('smtptype', '') !== 'none') {
            $rowsHtml .= (string) Html::frowSmall(
                __('legacy/usercp.row_email_notification'),
                '<input type=checkbox name=pmnotif'.(str_contains($notifs, '[pm]') ? ' checked' : '').' value=yes> '.htmlspecialchars(__('legacy/usercp.checkbox_notification_received_pm'))."<br />\n"
                .'<input type=checkbox name=emailnotif'.(str_contains($notifs, '[email]') ? ' checked' : '').' value="yes" /> '.htmlspecialchars(__('legacy/usercp.checkbox_notification_default_categories')),
                1,
                '',
                true
            );
        }

        $categoriesCell = $categories.'<div></div>'
            ."<table><caption><span class='big'>".htmlspecialchars(__('legacy/usercp.text_additional_selection')).'</span></caption><tr><td class=bottom><b>'.htmlspecialchars(__('legacy/usercp.text_show_dead_active')).'</b><br /><select name="incldead"><option value="0" '.(str_contains($notifs, '[incldead=0]') ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_including_dead')).'</option><option value="1" '.(str_contains($notifs, '[incldead=1]') || ! str_contains($notifs, 'incldead') ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_active')).'</option><option value="2" '.(str_contains($notifs, '[incldead=2]') ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_dead')).'</option></select></td><td class=bottom align=left><b>'.htmlspecialchars(__('legacy/usercp.text_show_special_torrents')).'</b><br /><select name="spstate"><option value="0" '.($specialState === 0 ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_all')).'</option>'.Html::promotionSelection($specialState).'</select></td><td class=bottom><b>'.htmlspecialchars(__('legacy/usercp.text_show_bookmarked')).'</b><br /><select name="inclbookmarked"><option value="0" '.(str_contains($notifs, '[inclbookmarked=0]') ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_all')).'</option><option value="1" '.(str_contains($notifs, '[inclbookmarked=1]') ? ' selected' : '').' >'.htmlspecialchars(__('legacy/usercp.select_bookmarked')).'</option><option value="2" '.(str_contains($notifs, '[inclbookmarked=2]') ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_bookmarked_exclude')).'</option></select></td></tr></table>';
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_browse_default_categories'), $categoriesCell, 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_theme'), "<select name=theme>\n".$themeOptions."\n</select>", 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_font_size'), '<select name=fontsize><option value=small '.(($curUser['fontsize'] ?? '') === 'small' ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_small')).'</option><option value=medium '.(($curUser['fontsize'] ?? '') === 'medium' ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_medium')).'</option><option value=large '.(($curUser['fontsize'] ?? '') === 'large' ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_large')).'</option></select>', 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_site_language'), "<select name=\"sitelanguage\">\n".$langOptions."\n</select>&nbsp;&nbsp;<span class='small'>".htmlspecialchars(__('legacy/usercp.text_translation_note')).'<a href="aboutnexus.php#translation"><b>'.htmlspecialchars(__('legacy/usercp.text_translation_link')).'</b></a></span>.', 1, '', true);

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_pm_boxes'), htmlspecialchars(__('legacy/usercp.text_show')).'<input type=text name=pmnum size=5 value='.(int) ($curUser['pmnum'] ?? 0).' >'.htmlspecialchars(__('legacy/usercp.text_pms_per_page')), 1, '', true);

        if ((string) $this->globals->get('showshoutbox_main', '') === 'yes') {
            $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_shoutbox'), htmlspecialchars(__('legacy/usercp.text_show_last')).'<input type=text name=sbnum size=5 value='.(int) ($curUser['sbnum'] ?? 0).' >'.htmlspecialchars(__('legacy/usercp.text_messages_at_shoutbox')).'<br />'.htmlspecialchars(__('legacy/usercp.text_refresh_shoutbox_every')).'<input type=text name=sbrefresh size=5 value='.(int) ($curUser['sbrefresh'] ?? 0).' >'.htmlspecialchars(__('legacy/usercp.text_seconds')), 1, '', true);
        }

        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_torrent_detail'), '<input type=checkbox name=showdescription'.(LegacyYesNo::isYes($curUser['showdescription'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_show_description')), 1, '', true);
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_discuss'), '<input type=checkbox name=showcomment'.(LegacyYesNo::isYes($curUser['showcomment'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_show_comments')), 1, '', true);
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_time_type'), '<input type=radio name=timetype '.(($curUser['timetype'] ?? '') === 'timeadded' ? ' checked' : '').' value=timeadded>'.htmlspecialchars(__('legacy/usercp.text_time_added')).'&nbsp;&nbsp;<input type=radio name=timetype '.(($curUser['timetype'] ?? '') === 'timealive' ? ' checked' : '').' value=timealive>'.htmlspecialchars(__('legacy/usercp.text_time_elapsed')).'<br />', 1, '', true);

        $browseCell = (__('legacy/usercp.text_browse_setting_warning')).'
<br /><b>'.(__('legacy/usercp.row_torrent_page')).': </b><br />'.htmlspecialchars(__('legacy/usercp.text_show')).'<input type=text size=5 name=torrentsperpage value='.(int) ($curUser['torrentsperpage'] ?? 0).'> '.htmlspecialchars(__('legacy/usercp.text_torrents_per_page')).htmlspecialchars(__('legacy/usercp.text_zero_equals_default')).'<br />'.
($showTooltipSetting ? '<b>'.htmlspecialchars(__('legacy/usercp.text_tooltip_type')).'</b>: <br /><input type=radio name=tooltip '.(($curUser['tooltip'] ?? '') === 'off' ? ' checked' : '').' value=off>'.htmlspecialchars(__('legacy/usercp.text_off')).'<br />' : '').
'<b>'.htmlspecialchars(__('legacy/usercp.text_append_words_to_torrents')).': </b><br /><input type=checkbox name=appendsticky '.(LegacyYesNo::isYes($curUser['appendsticky'] ?? null) ? ' checked' : '').' value=yes>'.(string) SafeHtml::fromUntrustedHtml(__('legacy/usercp.text_append_sticky')).'<br /><input type=checkbox name=appendnew '.(LegacyYesNo::isYes($curUser['appendnew'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_append_new')).'<br />'.htmlspecialchars(__('legacy/usercp.text_torrents_on_promotion')).'<input type=radio name=appendpromotion '.(($curUser['appendpromotion'] ?? '') === 'highlight' ? ' checked' : '')." value='highlight'>".htmlspecialchars(__('legacy/usercp.text_highlight')).'<input type=radio name=appendpromotion '.(($curUser['appendpromotion'] ?? '') === 'word' ? ' checked' : '')." value='word'>".htmlspecialchars(__('legacy/usercp.text_append_words')).'<input type=radio name=appendpromotion '.(($curUser['appendpromotion'] ?? '') === 'icon' ? ' checked' : '')." value='icon'>".(string) SafeHtml::fromUntrustedHtml(__('legacy/usercp.text_append_icon')).'<input type=radio name=appendpromotion '.(($curUser['appendpromotion'] ?? '') === 'off' ? ' checked' : '')." value='off'>".htmlspecialchars(__('legacy/usercp.text_no_mark')).'<br /><input type=checkbox name=appendpicked '.(LegacyYesNo::isYes($curUser['appendpicked'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_append_picked')).'<br />
<b>'.htmlspecialchars(__('legacy/usercp.text_show_action_icons')).': </b><br />'.'<input type=checkbox name=dlicon '.(LegacyYesNo::isYes($curUser['dlicon'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_show_download_icon')).' <img class="download" src="pic/trans.gif"  alt="Download" /><br /><input type=checkbox name=bmicon '.(LegacyYesNo::isYes($curUser['bmicon'] ?? null) ? ' checked' : '').' value=yes>'.htmlspecialchars(__('legacy/usercp.text_show_bookmark_icon')).' <img class="bookmark" src="pic/trans.gif" alt="Bookmark" /><br />
<b>'.htmlspecialchars(__('legacy/usercp.text_comments_reviews')).': </b><br /><input type=checkbox name=showcomnum '.(LegacyYesNo::isYes($curUser['showcomnum'] ?? null) ? ' checked' : '').' value=yes>'.(__('legacy/usercp.text_show_comment_number')).($showTooltipSetting ? '<select name="showlastcom"><option value="yes" '.(! LegacyYesNo::isNo($curUser['showlastcom'] ?? null) ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_with')).'</option><option value="no" '.(LegacyYesNo::isNo($curUser['showlastcom'] ?? null) ? ' selected' : '').'>'.htmlspecialchars(__('legacy/usercp.select_without')).'</option></select>'.(__('legacy/usercp.text_last_comment_on_tooltip')) : '');
        $rowsHtml .= (string) Html::frowSmall(__('legacy/usercp.row_browse_page'), $browseCell, 1, '', true);

        return [
            'formId' => 'form'.Strings::randomCode(6),
            'rowsHtml' => SafeHtml::fromTrustedHtml($rowsHtml),
        ];
    }
}
