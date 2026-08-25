<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Usercp\ForumSettingsDto;
use App\DTOs\Usercp\PersonalSettingsDto;
use App\DTOs\Usercp\SecuritySettingsDto;
use App\DTOs\Usercp\TrackerSettingsDto;
use App\Models\Comment;
use App\Models\Post;
use App\Models\SeedBoxRecord;
use App\Models\User;
use App\Services\WebAuthService;
use App\Support\AuthCookie;
use App\Support\Cache;
use App\Support\Hooks;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Mail;
use App\Support\PasswordHasher;
use App\Support\SupportContext;
use App\Support\Token;
use App\Support\TwoFactorAuthHelper;
use App\Support\Url;
use App\Support\Validators;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Nexus\Database\NexusDB;

final class UsercpRepository extends BaseRepository
{
    public static function getUserById(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }

    /**
     * @return array<int, array<string, mixed>>
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
                    $parts[] = Locale::trans("route-permission.{$ability}.text", [], null);
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
        return (bool) User::query()->where('id', $userId)->update(['last_offer' => date('Y-m-d H:i:s')]);
    }

    public static function emailExistsForOther(string $email, int $userId): bool
    {
        return User::query()->where('email', $email)->where('id', '!=', $userId)->exists();
    }

    public static function getChallenge(string $username): ?string
    {
        return CacheFacade::get(Token::challengeKey($username));
    }

    public static function deleteChallenge(string $username): bool
    {
        Cache::forgetWithLocales(Token::challengeKey($username));

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $allPost
     */
    public static function updateSecurity(int $userId, array $data, bool $resetAuthKey, array $allPost): bool
    {
        return (bool) NexusDB::transaction(function () use ($userId, $data, $resetAuthKey, $allPost) {
            self::updateUser($userId, $data);
            if ($resetAuthKey) {
                $torrentRep = new TorrentRepository;
                $torrentRep->resetTrackerReportAuthKeySecret($userId);
            }
            Hooks::doAction('usercp_security_update', $allPost);

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
     * @return array<int, int>
     */
    public static function getTableIds(string $table): array
    {
        return DB::table($table)->pluck('id')->all();
    }

    /**
     * @return Collection<int, SeedBoxRecord>
     */
    public static function getSeedBoxRecords(int $userId): Collection
    {
        return SeedBoxRecord::query()
            ->where('uid', $userId)
            ->where('type', SeedBoxRecord::TYPE_USER)
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getReadTopics(int $userId, int $limit = 5): array
    {
        return DB::table('readposts')
            ->join('topics', 'topics.id', '=', 'readposts.topicid')
            ->where('readposts.userid', $userId)
            ->orderByDesc('readposts.id')
            ->limit($limit)
            ->get(['topics.id as id', 'topics.userid', 'topics.subject', 'topics.lastpost', 'topics.views'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int, \stdClass>
     */
    public static function getCountryOptions(): array
    {
        return DB::table('countries')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * @return array<int, \stdClass>
     */
    public static function getBitbucketOptions(): array
    {
        return DB::table('bitbucket')
            ->where('public', '1')
            ->get()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public static function getStylesheetOptions(): array
    {
        return DB::table('stylesheets')
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
    public function updatePersonal(PersonalSettingsDto $dto): array
    {
        /** @var User $user */
        $user = Auth::user();

        $data = [
            'parked' => $dto->parked,
            'acceptpms' => $dto->acceptpms,
            'deletepms' => $dto->deletepms,
            'savepms' => $dto->savepms,
            'commentpm' => $dto->commentpm,
            'gender' => $dto->gender,
            'info' => $dto->info,
        ];

        if ($dto->notifs !== null) {
            $data['notifs'] = $dto->notifs;
        }

        if ($dto->country !== null) {
            $data['country'] = $dto->country;
        }

        if ($dto->trackerUrlId !== null) {
            $data['tracker_url_id'] = $dto->trackerUrlId;
        }

        if ($dto->avatar !== null) {
            $data['avatar'] = $dto->avatar;
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, (string) $user->passkey);

        return User::query()->find($user->id)?->toArray() ?? [];
    }

    /**
     * Update forum settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updateForum(ForumSettingsDto $dto): array
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Unauthenticated');
        }

        $data = [
            'topicsperpage' => $dto->topicsperpage,
            'postsperpage' => $dto->postsperpage,
            'avatars' => $dto->avatars,
            'signatures' => $dto->signatures,
            'clicktopic' => $dto->clicktopic !== '' ? $dto->clicktopic : $user->clicktopic,
            'signature' => $dto->signature,
        ];

        if ($dto->showlastpost !== null) {
            $data['showlastpost'] = $dto->showlastpost;
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, (string) $user->passkey);

        return User::query()->find($user->id)?->toArray() ?? [];
    }

    /**
     * Update tracker/browse settings for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function updateTracker(TrackerSettingsDto $dto): array
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Unauthenticated');
        }

        $notifsString = (string) $user->notifs;
        preg_match_all('/\[(.*)\]/Ui', $notifsString, $matches);
        $notifsArr = array_fill_keys($matches[1], 1);

        $dynamicPrefixes = array_merge(
            ['incldead=', 'spstate=', 'inclbookmarked='],
            ['cat', 'sou', 'med', 'cod', 'sta', 'pro', 'aud']
        );
        foreach (array_keys($notifsArr) as $key) {
            foreach ($dynamicPrefixes as $prefix) {
                if (str_starts_with((string) $key, $prefix)) {
                    unset($notifsArr[$key]);
                    break;
                }
            }
        }

        if ($dto->pmnotif) {
            $notifsArr['pm'] = 1;
        } else {
            unset($notifsArr['pm']);
        }

        if ($dto->emailnotif) {
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
                if ($dto->notifPreferences[$cbname.$id] ?? false) {
                    $notifsArr[$cbname.$id] = 1;
                }
            }
        }

        if ($dto->incldead !== null) {
            $notifsArr["incldead={$dto->incldead}"] = 1;
        }

        if ($dto->spstate !== null && $dto->spstate !== '') {
            $notifsArr["spstate={$dto->spstate}"] = 1;
        }

        if ($dto->inclbookmarked !== null && $dto->inclbookmarked !== '') {
            $notifsArr["inclbookmarked={$dto->inclbookmarked}"] = 1;
        }

        $data = [
            'notifs' => '['.implode('][', array_keys($notifsArr)).']',
            'torrentsperpage' => $dto->torrentsperpage,
            'timetype' => $dto->timetype,
            'appendsticky' => $dto->appendsticky,
            'appendnew' => $dto->appendnew,
            'appendpromotion' => $dto->appendpromotion,
            'appendpicked' => $dto->appendpicked,
            'dlicon' => $dto->dlicon,
            'bmicon' => $dto->bmicon,
            'showcomnum' => $dto->showcomnum,
            'showdescription' => $dto->showdescription,
            'showsmalldescr' => $dto->showsmalldescr,
            'showcomment' => $dto->showcomment,
            'pmnum' => $dto->pmnum,
            'sbnum' => $dto->sbnum,
            'sbrefresh' => $dto->sbrefresh,
            'fontsize' => $dto->fontsize,
        ];

        if ($dto->stylesheet !== null) {
            $data['stylesheet'] = $dto->stylesheet;
        }

        if ($dto->sitelanguage !== null) {
            $langFolder = Locale::folderForIdWithContext($dto->sitelanguage);
            $currentFolder = Locale::folderFromCookie($dto->currentLangFolder, false);
            if ($currentFolder !== $langFolder) {
                Locale::setFolderCookie($langFolder, 0x7FFFFFFF);
            }
            $data['lang'] = $dto->sitelanguage;
        }

        $showTooltip = (string) SupportContext::getGlobal('enabletooltip_tweak', '') === 'yes';
        if ($showTooltip) {
            $data['tooltip'] = $dto->tooltip ?? 'off';
            $data['showlastcom'] = $dto->showlastcom ?? 'no';
        }

        User::query()->where('id', $user->id)->update($data);
        Cache::clearUser($user->id, (string) $user->passkey);

        return User::query()->find($user->id)?->toArray() ?? [];
    }

    /**
     * Process the legacy usercp security "confirm" form and return the redirect URL.
     */
    public function updateSecurityFromLegacyRequest(Request $request): string
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Unauthenticated');
        }
        $lang = (array) (SupportContext::getGlobal('lang_usercp') ?? []);

        $response = (string) $request->input('response', '');
        $oldPassword = (string) $request->input('oldpassword', '');
        if ($response === '' && $oldPassword === '') {
            LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_enter_old_password'] ?? 'Please enter old password.'));
        }

        // For argon2id users, verify via plaintext password (sent over HTTPS)
        $userAlgo = (string) ($user->passhash_algo ?? PasswordHasher::ALGO_SHA256);
        if ($oldPassword !== '' && $userAlgo === PasswordHasher::ALGO_ARGON2ID) {
            if (! password_verify($oldPassword, (string) $user->passhash)) {
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_wrong_password_note'] ?? 'Wrong password.'));
            }
        } else {
            $challenge = self::getChallenge((string) $user->username);
            if (empty($challenge)) {
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), 'expired!');
            }

            $expectedResponse = hash_hmac('sha256', (string) $user->passhash, (string) $challenge);
            if (! hash_equals($expectedResponse, $response)) {
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_wrong_password_note'] ?? 'Wrong password.'));
            }
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
            $passhash = PasswordHasher::hash($chpassword);
            $data['passhash'] = $passhash;
            $data['passhash_algo'] = PasswordHasher::ALGO_ARGON2ID;
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
            $data['passkey'] = md5($user->username.date('Y-m-d H:i:s').$user->passhash);
        }

        $siteName = (string) SupportContext::getGlobal('SITENAME', '');
        $siteEmail = (string) SupportContext::getGlobal('SITEEMAIL', '');
        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');

        if ($changedemail === 1) {
            $sec = Token::randomHex(20);
            $hash = md5($sec.$email.$sec);
            $obemail = rawurlencode($email);
            $data['editsecret'] = $sec;

            $subject = $siteName.($lang['mail_profile_change_confirmation'] ?? '');
            $changeEmailOne = sprintf($lang['mail_change_email_one'] ?? '', $siteName);
            $changeEmailNine = sprintf($lang['mail_change_email_nine'] ?? '', $siteName);

            $body = $changeEmailOne.$user->username
                .($lang['mail_change_email_two'] ?? '').'('.$email.')'
                .($lang['mail_change_email_three'] ?? '')."\n\n"
                .($lang['mail_change_email_four'] ?? '').$request->ip()
                .($lang['mail_change_email_five'] ?? '')."\n\n"
                .($lang['mail_change_email_six'] ?? '')
                .'<b><a href="javascript:void(null)" onclick="window.open(\'http://'.$baseUrl.'/confirmemail.php/'.$user->id.'/'.$hash.'/'.$obemail.'\')">'.($lang['mail_here'] ?? '').'</a></b>'
                .($lang['mail_change_email_six_1'] ?? '').'<br />'."\n"
                .'http://'.$baseUrl.'/confirmemail.php/'.$user->id.'/'.$hash.'/'.$obemail."\n\n"
                .($lang['mail_change_email_seven'] ?? '')."\n\n"
                .'------'.($lang['mail_change_email_eight'] ?? '')."\n"
                .$changeEmailNine;

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
        self::deleteChallenge((string) $user->username);

        return $to;
    }

    /**
     * Update security settings for the authenticated user via API.
     *
     * @return array<string, mixed>
     */
    public function updateSecurityApi(SecuritySettingsDto $dto): array
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Unauthenticated');
        }

        if (! app(WebAuthService::class)->validatePassword($user, $dto->currentPassword)) {
            throw ValidationException::withMessages(['current_password' => ['Wrong password.']]);
        }

        $data = [];
        $changedemail = 0;
        $resetpasskey = $dto->resetpasskey;
        $resetAuthKey = $dto->resetauthkey;

        if ($dto->newPassword !== null && $dto->newPassword !== '') {
            $data['passhash'] = PasswordHasher::hash($dto->newPassword);
            $data['passhash_algo'] = PasswordHasher::ALGO_ARGON2ID;
            $data['auth_key'] = Token::randomHex(20);
        }

        $email = (string) ($dto->email ?? '');
        $disableEmailChange = (string) SupportContext::getGlobal('disableemailchange', 'no');
        $smtpType = (string) SupportContext::getGlobal('smtptype', 'none');
        $siteName = (string) SupportContext::getGlobal('SITENAME', '');
        $siteEmail = (string) SupportContext::getGlobal('SITEEMAIL', '');
        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');
        $lang = (array) (SupportContext::getGlobal('lang_usercp') ?? []);

        if ($disableEmailChange !== 'no' && $smtpType !== 'none' && $email !== '' && $email !== $user->email) {
            if (! Validators::isEmail($email)) {
                throw ValidationException::withMessages(['email' => [$lang['std_wrong_email_address_format'] ?? 'Wrong email format.']]);
            }

            if (self::emailExistsForOther($email, (int) $user->id)) {
                throw ValidationException::withMessages(['email' => [$lang['std_email_in_use'] ?? 'Email in use.']]);
            }

            $sec = Token::randomHex(20);
            $hash = md5($sec.$email.$sec);
            $obemail = rawurlencode($email);
            $data['editsecret'] = $sec;
            $changedemail = 1;

            $subject = $siteName.($lang['mail_profile_change_confirmation'] ?? '');
            $body = ($lang['mail_change_email_one'] ?? '').$user->username
                .($lang['mail_change_email_two'] ?? '').'('.$email.')'
                .($lang['mail_change_email_three'] ?? '')."\n\n"
                .($lang['mail_change_email_four'] ?? '').$dto->ip
                .($lang['mail_change_email_five'] ?? '')."\n\n"
                .($lang['mail_change_email_six'] ?? '')
                .'<b><a href="javascript:void(null)" onclick="window.open(\'http://'.$baseUrl.'/confirmemail.php/'.$user->id.'/'.$hash.'/'.$obemail.'\')">'.($lang['mail_here'] ?? '').'</a></b>'
                .($lang['mail_change_email_six_1'] ?? '').'<br />'."\n"
                .'http://'.$baseUrl.'/confirmemail.php/'.$user->id.'/'.$hash.'/'.$obemail."\n\n"
                .($lang['mail_change_email_seven'] ?? '')."\n\n"
                .'------'.($lang['mail_change_email_eight'] ?? '')."\n"
                .($lang['mail_change_email_nine'] ?? '');

            Mail::sentLegacy($email, $siteName, $siteEmail, $subject, str_replace('<br />', '<br />', nl2br($body)), 'profile change', false, false, '', 'UTF-8');
        }

        if ($resetpasskey) {
            $data['passkey'] = md5($user->username.date('Y-m-d H:i:s').$user->passhash);
        }

        if ($dto->twoStepCode !== null && $dto->twoStepCode !== '') {
            $secretToVerify = empty($user->two_step_secret) ? ($dto->twoStepSecret ?? '') : $user->two_step_secret;
            if ($secretToVerify === '' || ! TwoFactorAuthHelper::verifyCode($secretToVerify, $dto->twoStepCode)) {
                throw ValidationException::withMessages(['two_step_code' => ['Invalid two step code']]);
            }

            $data['two_step_secret'] = empty($user->two_step_secret) ? $secretToVerify : '';
        }

        if ($dto->privacy !== null && $dto->privacy !== '') {
            $privacy = in_array($dto->privacy, ['normal', 'low', 'strong'], true)
                ? $dto->privacy
                : 'normal';
            $data['privacy'] = $privacy;
        }

        if ($data !== []) {
            self::updateSecurity((int) $user->id, $data, $resetAuthKey, $dto->allInputs);
            Cache::clearUser($user->id, '');
        }

        return User::query()->find($user->id)?->toArray() ?? [];
    }
}
