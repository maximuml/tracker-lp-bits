<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Usercp\SecuritySettingsDto;
use App\Enums\UserPrivacy;
use App\Models\User;
use App\Services\WebAuthService;
use App\Support\AuthCookie;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Globals;
use App\Support\Http;
use App\Support\LegacyResponse;
use App\Support\Mail;
use App\Support\PasswordHasher;
use App\Support\Security\PasskeyGenerator;
use App\Support\Token;
use App\Support\TwoFactorAuthHelper;
use App\Support\Url;
use App\Support\Validators;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Security-related user control panel commands.
 */
final class UsercpSecurityCommand
{
    public function __construct(
        private readonly Globals $globals,
        private readonly PasskeyGenerator $passkeyGenerator,
        private readonly TorrentDownloadRepository $torrentDownloadRepository,
        private readonly WebAuthService $webAuthService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUser(int $userId, array $data): bool
    {
        return (bool) User::query()->where('id', $userId)->update($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSecurity(int $userId, array $data, bool $resetAuthKey): bool
    {
        return (bool) DB::transaction(function () use ($userId, $data, $resetAuthKey) {
            User::query()->where('id', $userId)->update($data);
            if ($resetAuthKey) {
                $this->torrentDownloadRepository->resetTrackerReportAuthKeySecret($userId);
            }

            return true;
        });
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
        $lang = (array) ($this->globals->get('lang_usercp') ?? []);

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
            $challenge = $this->getChallenge((string) $user->username);
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

        $config = SiteConfig::current();
        $disableEmailChange = $config->security->disableEmailChange(false) ? 'yes' : 'no';
        $smtpType = $config->smtp->type('none');

        if ($disableEmailChange !== 'no' && $smtpType !== 'none' && $email !== '' && $email !== $user->email) {
            if (! Validators::isEmail($email)) {
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_wrong_email_address_format'] ?? 'Wrong email format.'));
            }

            if ($this->emailExistsForOther($email, (int) $user->id)) {
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_email_in_use'] ?? 'Email in use.'));
            }

            $changedemail = 1;
        }

        if ($resetpasskey === 1) {
            $data['passkey'] = $this->passkeyGenerator->generate();
        }

        $siteName = $config->basic->siteName();
        $siteEmail = $config->main->siteEmail();
        $baseUrl = $config->basic->baseUrl();
        $scheme = Http::protocolPrefix(Url::isSecure());

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
                .'<b><a href="javascript:void(null)" onclick="window.open(\''.$scheme.$baseUrl.'/confirmemail.php/'.$user->id.'/'.$hash.'/'.$obemail.'\')">'.($lang['mail_here'] ?? '').'</a></b>'
                .($lang['mail_change_email_six_1'] ?? '').'<br />'."\n"
                .$scheme.$baseUrl.'/confirmemail.php/'.$user->id.'/'.$hash.'/'.$obemail."\n\n"
                .($lang['mail_change_email_seven'] ?? '')."\n\n"
                .'------'.($lang['mail_change_email_eight'] ?? '')."\n"
                .$changeEmailNine;

            Mail::sentLegacy($email, $siteName, $siteEmail, $subject, str_replace('<br />', '<br />', nl2br($body)), 'profile change', false, false, '', 'UTF-8');
        }

        if (! in_array($privacy, ['normal', 'low', 'strong'], true)) {
            $privacy = 'normal';
        }

        $data['privacy'] = UserPrivacy::fromStringSafe($privacy)->value;
        if ($user->privacy !== UserPrivacy::fromStringSafe($privacy)) {
            $privacyupdated = 1;
        }

        $this->updateSecurity((int) $user->id, $data, $resetAuthKey);

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
        $this->deleteChallenge((string) $user->username);

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

        if (! $this->webAuthService->validatePassword($user, $dto->currentPassword)) {
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
        $config = SiteConfig::current();
        $disableEmailChange = $config->security->disableEmailChange(false) ? 'yes' : 'no';
        $smtpType = $config->smtp->type('none');
        $siteName = $config->basic->siteName();
        $siteEmail = $config->main->siteEmail();
        $baseUrl = $config->basic->baseUrl();
        $scheme = Http::protocolPrefix(Url::isSecure());
        $lang = (array) ($this->globals->get('lang_usercp') ?? []);

        if ($disableEmailChange !== 'no' && $smtpType !== 'none' && $email !== '' && $email !== $user->email) {
            if (! Validators::isEmail($email)) {
                throw ValidationException::withMessages(['email' => [$lang['std_wrong_email_address_format'] ?? 'Wrong email format.']]);
            }

            if ($this->emailExistsForOther($email, (int) $user->id)) {
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
                .'<b><a href="javascript:void(null)" onclick="window.open(\''.$scheme.$baseUrl.'/confirmemail.php/'.$user->id.'/'.$hash.'/'.$obemail.'\')">'.($lang['mail_here'] ?? '').'</a></b>'
                .($lang['mail_change_email_six_1'] ?? '').'<br />'."\n"
                .$scheme.$baseUrl.'/confirmemail.php/'.$user->id.'/'.$hash.'/'.$obemail."\n\n"
                .($lang['mail_change_email_seven'] ?? '')."\n\n"
                .'------'.($lang['mail_change_email_eight'] ?? '')."\n"
                .($lang['mail_change_email_nine'] ?? '');

            Mail::sentLegacy($email, $siteName, $siteEmail, $subject, str_replace('<br />', '<br />', nl2br($body)), 'profile change', false, false, '', 'UTF-8');
        }

        if ($resetpasskey) {
            $data['passkey'] = $this->passkeyGenerator->generate();
        }

        if ($dto->twoStepCode !== null && $dto->twoStepCode !== '') {
            $secretToVerify = empty($user->two_step_secret) ? ($dto->twoStepSecret ?? '') : $user->two_step_secret;
            if ($secretToVerify === '' || ! TwoFactorAuthHelper::verifyCode($secretToVerify, $dto->twoStepCode)) {
                throw ValidationException::withMessages(['two_step_code' => ['Invalid two step code']]);
            }

            $data['two_step_secret'] = empty($user->two_step_secret) ? $secretToVerify : '';
        }

        if ($dto->privacy !== null && $dto->privacy !== '') {
            $data['privacy'] = UserPrivacy::fromStringSafe($dto->privacy)->value;
        }

        if ($data !== []) {
            $this->updateSecurity((int) $user->id, $data, $resetAuthKey);
            Cache::clearUser($user->id, '');
        }

        return User::query()->find($user->id)?->toApiArray() ?? [];
    }

    public function getChallenge(string $username): ?string
    {
        return CacheFacade::get(Token::challengeKey($username));
    }

    public function deleteChallenge(string $username): bool
    {
        Cache::forgetWithLocales(Token::challengeKey($username));

        return true;
    }

    public function emailExistsForOther(string $email, int $userId): bool
    {
        return User::query()->where('email', $email)->where('id', '!=', $userId)->exists();
    }
}
