<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\SeedBoxRecord;
use App\Models\User;
use App\Support\AuthCookie;
use App\Support\Cache;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Mail;
use App\Support\SupportContext;
use App\Support\Token;
use App\Support\TwoFactorAuthHelper;
use App\Support\Url;
use App\Support\Validators;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Nexus\Database\NexusDB;

final class UsercpRepository extends BaseRepository
{
    public static function getUserById(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    public static function getUserTokens(User $user): array
    {
        $tokens = [];
        foreach ($user->tokens()->orderBy('id', 'desc')->get() as $token) {
            $abilities = $token->abilities ?? [];
            if (in_array('*', $abilities, true)) {
                $abilitiesText = 'ALL';
            } else {
                $parts = [];
                foreach ($abilities as $ability) {
                    $parts[] = \App\Support\Locale::trans("route-permission.{$ability}.text", [], null);
                }
                $abilitiesText = implode(', ', $parts);
            }

            $tokens[] = [
                'id' => $token->id,
                'name' => $token->name,
                'abilitiesText' => $abilitiesText,
                'created_at' => $token->created_at,
            ];
        }

        return $tokens;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function updateUser(int $userId, array $data): bool
    {
        return (bool) User::query()->where('id', $userId)->update($data);
    }

    public static function updateLastOffer(int $userId): bool
    {
        return (bool) User::query()->where('id', $userId)->update(['last_offer' => date("Y-m-d H:i:s")]);
    }

    public static function emailExistsForOther(string $email, int $userId): bool
    {
        return User::query()->where('email', $email)->where('id', '!=', $userId)->exists();
    }

    public static function getChallenge(string $username): ?string
    {
        return \Nexus\Database\NexusDB::cache_get(\App\Support\Token::challengeKey($username));
    }

    public static function deleteChallenge(string $username): bool
    {
        return (bool) \Nexus\Database\NexusDB::cache_del(\App\Support\Token::challengeKey($username));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $allPost
     */
    public static function updateSecurity(int $userId, array $data, bool $resetAuthKey, array $allPost): bool
    {
        return (bool) \Nexus\Database\NexusDB::transaction(function () use ($userId, $data, $resetAuthKey, $allPost) {
            self::updateUser($userId, $data);
            if ($resetAuthKey) {
                $torrentRep = new \App\Repositories\TorrentRepository();
                $torrentRep->resetTrackerReportAuthKeySecret($userId);
            }
            \App\Support\Hooks::doAction("usercp_security_update", $allPost);

            return true;
        });
    }

    public static function getCommentCount(int $userId): int
    {
        return (int) Comment::query()->where('user', $userId)->count();
    }

    public static function getForumPostCount(int $userId): int
    {
        return (int) Post::query()->where('userid', $userId)->count();
    }

    public static function getTotalPostCount(): int
    {
        return (int) Post::query()->count();
    }

    public static function getTopicPostCount(int $topicId): int
    {
        return (int) Post::query()->where('topicid', $topicId)->count();
    }

    /**
     * @return  array<int, int>
     */
    public static function getTableIds(string $table): array
    {
        return NexusDB::table($table)->pluck('id')->all();
    }

    /**
     * @return  Collection<int, SeedBoxRecord>
     */
    public static function getSeedBoxRecords(int $userId): Collection
    {
        return SeedBoxRecord::query()
            ->where('uid', $userId)
            ->where('type', SeedBoxRecord::TYPE_USER)
            ->get();
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    public static function getReadTopics(int $userId, int $limit = 5): array
    {
        return NexusDB::table('readposts')
            ->join('topics', 'topics.id', '=', 'readposts.topicid')
            ->where('readposts.userid', $userId)
            ->orderByDesc('readposts.id')
            ->limit($limit)
            ->get(['topics.id as id', 'topics.userid', 'topics.subject', 'topics.lastpost', 'topics.views'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }
    /**
     * @return  array<int, \stdClass>
     */
    public static function getCountryOptions(): array
    {
        return NexusDB::table('countries')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * @return  array<int, \stdClass>
     */
    public static function getBitbucketOptions(): array
    {
        return NexusDB::table('bitbucket')
            ->where('public', '1')
            ->get()
            ->all();
    }

    /**
     * @return  array<string, int>
     */
    public static function getStylesheetOptions(): array
    {
        return NexusDB::table('stylesheets')
            ->orderBy('name')
            ->pluck('id', 'name')
            ->all();
    }
    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->toArray();
    }

    /**
     * Update personal settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updatePersonal(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        $data = [];

        $data['parked'] = $request->input('parked') === 'yes' ? 'yes' : 'no';
        $data['acceptpms'] = in_array((string) $request->input('acceptpms'), ['yes', 'friends', 'no'], true)
            ? (string) $request->input('acceptpms')
            : 'yes';
        $data['deletepms'] = $request->has('deletepms') ? 'yes' : 'no';
        $data['savepms'] = $request->has('savepms') ? 'yes' : 'no';
        $data['commentpm'] = $request->input('commentpm') === 'yes' ? 'yes' : 'no';
        $data['gender'] = in_array((string) $request->input('gender'), ['N/A', 'Male', 'Female'], true)
            ? (string) $request->input('gender')
            : 'N/A';

        $country = (int) $request->input('country', 0);
        if (Validators::isId($country)) {
            $data['country'] = $country;
        }

        $trackerUrlId = (int) $request->input('tracker_url_id', 0);
        if (Validators::isId($trackerUrlId)) {
            $data['tracker_url_id'] = $trackerUrlId;
        }

        $avatar = (string) $request->input('avatar', '');
        if ($avatar === '') {
            $avatar = (string) $request->input('savatar', '');
        }
        if (preg_match('/^https?:\/\/[^\s\'"<>]+\.(jpg|gif|png|jpeg)$/i', $avatar)
            && ! preg_match('/\.php/i', $avatar)
            && ! preg_match('/\.js/i', $avatar)
            && ! preg_match('/\.cgi/i', $avatar)) {
            $data['avatar'] = htmlspecialchars(trim($avatar));
        }

        $data['info'] = htmlspecialchars(trim((string) $request->input('info', '')));

        $notifs = $request->input('notifs');
        if (is_array($notifs) || is_string($notifs)) {
            $notifsArr = [];
            if (is_array($notifs)) {
                foreach (User::$notificationOptions as $option) {
                    if (isset($notifs[$option]) && $notifs[$option]) {
                        $notifsArr[$option] = 1;
                    }
                }
            }
            $data['notifs'] = '[' . implode('][', array_keys($notifsArr)) . ']';
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, $user->passkey);

        return User::query()->find($user->id)?->toArray() ?? [];
    }

    /**
     * Update forum settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updateForum(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        $data = [
            'topicsperpage' => max(0, min(100, (int) $request->input('topicsperpage', 0))),
            'postsperpage' => max(0, min(100, (int) $request->input('postsperpage', 0))),
            'avatars' => $request->input('avatars') === 'yes' ? 'yes' : 'no',
            'signatures' => $request->input('signatures') === 'yes' ? 'yes' : 'no',
            'clicktopic' => in_array((string) $request->input('clicktopic'), ['firstpage', 'lastpage'], true)
                ? (string) $request->input('clicktopic')
                : $user->clicktopic,
            'signature' => htmlspecialchars(trim((string) $request->input('signature', ''))),
        ];

        if ($request->has('ttlastpost')) {
            $data['showlastpost'] = $request->input('ttlastpost') === 'yes' ? 'yes' : 'no';
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, $user->passkey);

        return User::query()->find($user->id)?->toArray() ?? [];
    }

    /**
     * Update tracker/browse settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updateTracker(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        $notifsString = (string) $user->notifs;
        preg_match_all('/\[(.*)\]/Ui', $notifsString, $matches);
        $notifsArr = array_fill_keys($matches[1], 1);

        foreach (array_keys($notifsArr) as $key) {
            foreach (['incldead', 'spstate', 'inclbookmarked'] as $prefix) {
                if (str_starts_with((string) $key, $prefix)) {
                    unset($notifsArr[$key]);
                    break;
                }
            }
        }

        if ($request->input('pmnotif') === 'yes') {
            $notifsArr['pm'] = 1;
        } else {
            unset($notifsArr['pm']);
        }

        if ($request->input('emailnotif') === 'yes') {
            $notifsArr['email'] = 1;
        } else {
            unset($notifsArr['email']);
        }

        foreach ([
            'categories' => 'cat',
            'sources' => 'sou',
            'media' => 'med',
            'codecs' => 'cod',
            'standards' => 'sta',
            'processings' => 'pro',
            'audiocodecs' => 'aud',
        ] as $table => $cbname) {
            foreach (self::getTableIds($table) as $id) {
                if ($request->input($cbname . $id) === 'yes') {
                    $notifsArr[$cbname . $id] = 1;
                } else {
                    unset($notifsArr[$cbname . $id]);
                }
            }
        }

        $incldead = $request->input('incldead');
        if ($incldead !== null && $incldead != 1) {
            $notifsArr["incldead=$incldead"] = 1;
        }

        $spstate = $request->input('spstate');
        if ($spstate) {
            $notifsArr["spstate=$spstate"] = 1;
        }

        $inclbookmarked = $request->input('inclbookmarked');
        if ($inclbookmarked) {
            $notifsArr["inclbookmarked=$inclbookmarked"] = 1;
        }

        $data = [
            'notifs' => '[' . implode('][', array_keys($notifsArr)) . ']',
        ];

        $stylesheet = (int) $request->input('stylesheet', 0);
        if (Validators::isId($stylesheet)) {
            $data['stylesheet'] = $stylesheet;
        }

        $sitelanguage = (int) $request->input('sitelanguage', 0);
        if (Validators::isId($sitelanguage)) {
            $langFolder = Locale::folderForIdWithContext($sitelanguage);
            $currentFolder = Locale::folderFromCookie($request->cookie('c_lang_folder') ?? '', false);
            if ($currentFolder !== $langFolder) {
                Locale::setFolderCookie($langFolder, 0x7fffffff);
            }
            $data['lang'] = $sitelanguage;
        }

        $data['torrentsperpage'] = max(0, min(100, (int) $request->input('torrentsperpage', 0)));
        $data['timetype'] = (string) $request->input('timetype', '');
        $data['appendsticky'] = $request->input('appendsticky') === 'yes' ? 'yes' : 'no';
        $data['appendnew'] = $request->input('appendnew') === 'yes' ? 'yes' : 'no';
        $data['appendpromotion'] = (string) $request->input('appendpromotion', '');
        $data['appendpicked'] = $request->input('appendpicked') === 'yes' ? 'yes' : 'no';
        $data['dlicon'] = $request->input('dlicon') === 'yes' ? 'yes' : 'no';
        $data['bmicon'] = $request->input('bmicon') === 'yes' ? 'yes' : 'no';
        $data['showcomnum'] = $request->input('showcomnum') === 'yes' ? 'yes' : 'no';
        $data['showdescription'] = $request->input('showdescription') === 'yes' ? 'yes' : 'no';
        $data['showsmalldescr'] = $request->input('smalldescr') === 'yes' ? 'yes' : 'no';
        $data['showcomment'] = $request->input('showcomment') === 'yes' ? 'yes' : 'no';
        $data['pmnum'] = max(1, min(100, (int) $request->input('pmnum', 20)));
        $data['sbnum'] = max(10, min(500, (int) $request->input('sbnum', 70)));
        $data['sbrefresh'] = max(10, min(3600, (int) $request->input('sbrefresh', 120)));

        $showTooltip = (string) SupportContext::getGlobal('enabletooltip_tweak', '') === 'yes';
        if ($showTooltip) {
            $data['tooltip'] = (string) $request->input('tooltip', '');
            $data['showlastcom'] = $request->input('showlastcom') === 'yes' ? 'yes' : 'no';
        }

        $fontsize = (string) $request->input('fontsize', '');
        if (in_array($fontsize, ['small', 'medium', 'large'], true)) {
            $data['fontsize'] = $fontsize;
        } else {
            $data['fontsize'] = 'medium';
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, $user->passkey);

        return User::query()->find($user->id)?->toArray() ?? [];
    }

    /**
     * Process the legacy usercp security "confirm" form and return the redirect URL.
     */
    public function updateSecurityFromLegacyRequest(Request $request): string
    {
        /** @var User $user */
        $user = Auth::user();
        $lang = (array) (SupportContext::getGlobal('lang_usercp') ?? []);

        $response = (string) $request->input('response', '');
        if ($response === '') {
            LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_enter_old_password'] ?? 'Please enter old password.'));
        }

        $challenge = self::getChallenge($user->username);
        if (empty($challenge)) {
            LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), 'expired!');
        }

        $expectedResponse = hash_hmac('sha256', (string) $user->passhash, (string) $challenge);
        if (! hash_equals($expectedResponse, $response)) {
            LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_wrong_password_note'] ?? 'Wrong password.'));
        }

        $data = [];
        $changedemail = 0;
        $passupdated = 0;
        $privacyupdated = 0;
        $resetpasskey = $request->input('resetpasskey') == 1 ? 1 : 0;
        $resetAuthKey = $request->input('resetauthkey') == 1;

        $email = htmlspecialchars(trim((string) $request->input('email', '')));
        $chpassword = (string) $request->input('chpassword', '');
        $privacy = (string) $request->input('privacy', '');

        $twoStepSecret = (string) ($request->input('two_step_secret') ?? '');
        $twoStepSecretHash = (string) ($request->input('two_step_code') ?? '');

        if ($twoStepSecretHash !== '') {
            if (empty($user->two_step_secret)) {
                $secretToVerify = $twoStepSecret;
                $data['two_step_secret'] = $twoStepSecret;
            } else {
                $secretToVerify = $user->two_step_secret;
                $data['two_step_secret'] = '';
            }

            if (! TwoFactorAuthHelper::verifyCode($secretToVerify, $twoStepSecretHash)) {
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), 'Invalid two step code');
            }
        }

        if ($chpassword !== '') {
            $sec = Token::randomHex(20);
            $passhash = hash('sha256', $sec . $chpassword);
            $data['secret'] = $sec;
            $data['passhash'] = $passhash;
            $authKey = Token::randomHex(20);
            $data['auth_key'] = $authKey;

            AuthCookie::setLoginCookie((int) $user->id, $authKey, 0);
            $passupdated = 1;
        }

        $disableEmailChange = (string) SupportContext::getGlobal('disableemailchange', 'no');
        $smtpType = (string) SupportContext::getGlobal('smtptype', 'none');

        if ($disableEmailChange !== 'no' && $smtpType !== 'none' && $email !== '' && $email !== $user->email) {
            if (! Validators::isEmail($email)) {
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_wrong_email_address_format'] ?? 'Wrong email format.'));
            }

            if (self::emailExistsForOther($email, (int) $user->id)) {
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_email_in_use'] ?? 'Email in use.'));
            }

            $changedemail = 1;
        }

        if ($resetpasskey === 1) {
            $data['passkey'] = md5($user->username . date('Y-m-d H:i:s') . $user->passhash);
        }

        $siteName = (string) SupportContext::getGlobal('SITENAME', '');
        $siteEmail = (string) SupportContext::getGlobal('SITEEMAIL', '');
        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');

        if ($changedemail === 1) {
            $sec = Token::randomHex(20);
            $hash = md5($sec . $email . $sec);
            $obemail = rawurlencode($email);
            $data['editsecret'] = $sec;

            $subject = $siteName . ($lang['mail_profile_change_confirmation'] ?? '');
            $changeEmailOne = sprintf($lang['mail_change_email_one'] ?? '', $siteName);
            $changeEmailNine = sprintf($lang['mail_change_email_nine'] ?? '', $siteName);

            $body = $changeEmailOne . $user->username
                . ($lang['mail_change_email_two'] ?? '') . '(' . $email . ')'
                . ($lang['mail_change_email_three'] ?? '') . "\n\n"
                . ($lang['mail_change_email_four'] ?? '') . $request->ip()
                . ($lang['mail_change_email_five'] ?? '') . "\n\n"
                . ($lang['mail_change_email_six'] ?? '')
                . '<b><a href="javascript:void(null)" onclick="window.open(\'http://' . $baseUrl . '/confirmemail.php/' . $user->id . '/' . $hash . '/' . $obemail . '\')">' . ($lang['mail_here'] ?? '') . '</a></b>'
                . ($lang['mail_change_email_six_1'] ?? '') . '<br />' . "\n"
                . 'http://' . $baseUrl . '/confirmemail.php/' . $user->id . '/' . $hash . '/' . $obemail . "\n\n"
                . ($lang['mail_change_email_seven'] ?? '') . "\n\n"
                . '------' . ($lang['mail_change_email_eight'] ?? '') . "\n"
                . $changeEmailNine;

            Mail::sentLegacy($email, $siteName, $siteEmail, $subject, str_replace('<br />', '<br />', nl2br($body)), 'profile change', false, false, '', 'UTF-8');
        }

        if (! in_array($privacy, ['normal', 'low', 'strong'], true)) {
            $privacy = 'normal';
        }

        $data['privacy'] = $privacy;
        if ($user->privacy !== $privacy) {
            $privacyupdated = 1;
        }

        self::updateSecurity((int) $user->id, $data, $resetAuthKey, (array) $request->all());

        $to = 'usercp.php?action=security&type=saved';
        if ($changedemail === 1) {
            $to .= '&mail=1';
        }
        if ($resetpasskey === 1) {
            $to .= '&passkey=1';
        }
        if ($passupdated === 1) {
            $to .= '&password=1';
        }
        if ($privacyupdated === 1) {
            $to .= '&privacy=1';
        }

        Cache::clearUser($user->id, '');
        self::deleteChallenge($user->username);

        return $to;
    }
}
