<?php

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Support\Cache;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\Email;
use App\Support\Http;
use App\Support\Mail;
use App\Support\PasswordHasher;
use App\Support\Strings;
use App\Support\Token;
use App\Support\Url;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;

/**
 * Handles the legacy recover.php password reset flow.
 */
class PasswordRecoveryService
{
    private const NEW_PASSWORD_LENGTH = 10;

    private const RECOVER_CACHE_TTL = 3600;

    public function __construct(
        private WebAuthService $authService,
    ) {}

    /**
     * Request a password reset email.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $langRecover
     * @param  array<string, string>  $langFunctions
     */
    public function requestReset(array $data, string $ip, array $langRecover, array $langFunctions): void
    {
        $this->authService->assertNotBanned($ip);
        $this->verifyCaptcha($data, $ip, $langFunctions);

        $email = Email::sanitizeForDisplay(trim((string) ($data['email'] ?? '')));

        if ($email === '') {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException($this->msg($langRecover, 'std_missing_email_address', 'You must enter an email address!'));
        }

        if (! Email::isWellFormed($email)) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException($this->msg($langRecover, 'std_invalid_email_address', 'Invalid email address!'));
        }

        $user = (array) DB::table('users')
            ->whereRaw('BINARY email = ?', [$email])
            ->first();

        if (empty($user)) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException($this->msg($langRecover, 'std_email_not_in_database', 'The email address was not found in the database.'));
        }

        if (($user['status'] ?? '') === 'pending') {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException($this->msg($langRecover, 'std_user_account_unconfirmed', 'The account has not been verified yet.'));
        }

        $sec = Token::randomHex();

        $affected = User::query()->where('id', (int) $user['id'])->update(['editsecret' => $sec]);

        if (! $affected) {
            throw new AuthenticationException($this->msg($langRecover, 'std_database_error', 'Database error. Please contact an administrator about this.'));
        }

        Cache::clearUser((int) $user['id'], '');

        $hash = md5($sec.$email.$user['passhash'].$sec);

        CacheFacade::put("recover:$hash", now()->toDateTimeString(), self::RECOVER_CACHE_TTL);

        $this->sendResetRequestEmail($email, (int) $user['id'], $hash, $ip, $langRecover);
    }

    /**
     * Verify a password reset link and reset the user's password.
     *
     * @param  array<string, string>  $langRecover
     */
    public function resetPassword(int $id, string $md5, array $langRecover): string
    {
        if (! CacheFacade::get("recover:$md5")) {
            throw new AuthenticationException($this->msg($langRecover, 'std_unable_updating_user_data', 'The reset link is expired or invalid.'));
        }

        $user = User::query()->find($id, ['id', 'username', 'email', 'passhash', 'editsecret']);

        if (! $user) {
            throw new AuthenticationException($this->msg($langRecover, 'std_unable_updating_user_data', 'Unable to update user data.'));
        }

        $email = $user->email;
        $sec = Strings::padHash($user->editsecret);

        if ($md5 !== md5($sec.$email.$user->passhash.$sec)) {
            throw new AuthenticationException($this->msg($langRecover, 'std_unable_updating_user_data', 'The reset link is invalid.'));
        }

        Cache::forgetWithLocales("recover:$md5");

        $newPassword = $this->generateRandomPassword();
        $newSecret = Token::randomHex();
        $newPasshash = PasswordHasher::hash($newPassword);
        $authKey = Token::randomHex();

        $affected = User::query()->where('id', $id)->where('editsecret', $user->editsecret)->update([
            'secret' => $newSecret,
            'editsecret' => '',
            'passhash' => $newPasshash,
            'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
            'auth_key' => $authKey,
        ]);

        if (! $affected) {
            throw new AuthenticationException($this->msg($langRecover, 'std_unable_updating_user_data', 'Unable to update user data.'));
        }

        Cache::clearUser($id, '');

        $this->sendNewPasswordEmail($user, $newPassword, $langRecover);

        return $newPassword;
    }

    /**
     * @param  array<string, string>  $langRecover
     */
    private function sendResetRequestEmail(string $email, int $userId, string $hash, string $ip, array $langRecover): void
    {
        $baseUrl = SiteConfig::current()->basic->baseUrl();
        if (! str_contains($baseUrl, '://')) {
            $baseUrl = Http::protocolPrefix(Url::isSecure()).$baseUrl;
        }
        $baseUrl = rtrim($baseUrl, '/');
        $siteName = SiteConfig::current()->basic->siteName();

        $mailOne = $langRecover['mail_one'] ?? 'Hi,<br /><br />Someone, hopefully you, requested that the password for the account<br />associated with this email address ';
        $mailTwo = $langRecover['mail_two'] ?? ' be reset.<br /><br />The request originated from ';
        $mailThree = $langRecover['mail_three'] ?? '.<br /><br />If you did not do this ignore this email. Please do not reply.<br /><br />Should you wish to confirm this request, please follow ';
        $mailFour = sprintf($langRecover['mail_four'] ?? '<br />After you do this, your password will be reset and emailed back to you.<br /><br />------<br />Yours,<br />The %s Team.', $siteName);
        $thisLink = $langRecover['mail_this_link'] ?? 'THIS LINK';

        $resetUrl = $baseUrl.'/recover.php?id='.$userId.'&secret='.$hash;

        $body = $mailOne
            .'('.htmlspecialchars($email).')'
            .$mailTwo
            .htmlspecialchars($ip)
            .$mailThree
            .'<b><a href="'.$resetUrl.'" target="_blank"> '.$thisLink.' </a></b><br />'
            .$resetUrl
            .$mailFour;

        Mail::sentLegacy(
            $email,
            $siteName,
            SiteConfig::current()->main->siteEmail(''),
            $siteName.$this->msg($langRecover, 'mail_title', ' password reset confirmation'),
            $body,
            'confirmation',
            true,
            false,
            '',
            'UTF-8',
        );
    }

    /**
     * @param  array<string, string>  $langRecover
     */
    private function sendNewPasswordEmail(User $user, string $newPassword, array $langRecover): void
    {
        $baseUrl = SiteConfig::current()->basic->baseUrl();
        if (! str_contains($baseUrl, '://')) {
            $baseUrl = Http::protocolPrefix(Url::isSecure()).$baseUrl;
        }
        $baseUrl = rtrim($baseUrl, '/');
        $siteName = SiteConfig::current()->basic->siteName();

        $mailTwoFour = sprintf($langRecover['mail_two_four'] ?? '<br /><br />You may change your password in User CP - Security Settings after logging in.<br />------<br />Yours,<br />The %s Team.', $siteName);

        $body = ($langRecover['mail_two_one'] ?? 'Hi,<br /><br />As per your request we have generated a new password for your account.<br /><br />Here is the information we now have on file for this account:<br /><br />User name: ')
            .(string) $user->username
            .($langRecover['mail_two_two'] ?? '<br />Password:  ')
            .$newPassword
            .($langRecover['mail_two_three'] ?? '<br /><br />You may login from ')
            .'<b><a href="'.$baseUrl.'/login.php">'.($langRecover['mail_here'] ?? 'HERE').'</a></b>'
            .$mailTwoFour;

        Mail::sentLegacy(
            (string) $user->email,
            $siteName,
            SiteConfig::current()->main->siteEmail(''),
            $siteName.$this->msg($langRecover, 'mail_two_title', ' account details'),
            $body,
            'details',
            true,
            false,
            '',
            'UTF-8',
        );
    }

    private function generateRandomPassword(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        $maxIndex = strlen($chars) - 1;

        for ($i = 0; $i < self::NEW_PASSWORD_LENGTH; $i++) {
            $password .= $chars[random_int(0, $maxIndex)];
        }

        return $password;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $langFunctions
     */
    private function verifyCaptcha(array $data, string $ip, array $langFunctions): void
    {
        if (! $this->authService->isCaptchaEnabled()) {
            return;
        }

        $payload = [
            'imagehash' => (string) ($data['imagehash'] ?? ''),
            'imagestring' => (string) ($data['imagestring'] ?? ''),
            'request' => $data,
        ];

        try {
            $verified = Captcha::manager()->driver()->verify($payload, ['ip' => $ip]);
        } catch (\Throwable $exception) {
            $verified = false;
        }

        if (! $verified) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException($this->msg($langFunctions, 'std_invalid_image_code', 'Invalid captcha response.'));
        }
    }

    /**
     * @param  array<string, string>  $lang
     */
    private function msg(array $lang, string $key, string $fallback): string
    {
        return (string) ($lang[$key] ?? $fallback);
    }
}
