<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Support\Cache;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\Email;
use App\Support\Http;
use App\Support\Mail;
use App\Support\PasswordHasher;
use App\Support\Token;
use App\Support\Url;
use Illuminate\Support\Facades\DB;

/**
 * Handles the password reset flow via SecureTokenService (CSPRNG + SHA-256 digest).
 */
class PasswordRecoveryService
{
    private const NEW_PASSWORD_LENGTH = 10;

    private const RECOVERY_TOKEN_TABLE = 'password_recovery_tokens';

    public function __construct(
        private WebAuthService $authService,
        private SecureTokenService $tokenService,
        private readonly OutboxService $outboxService = new OutboxService,
    ) {}

    /**
     * Request a password reset email.
     *
     * @param  array<string, mixed>  $data
     */
    public function requestReset(array $data, string $ip): void
    {
        $this->authService->assertNotBanned($ip);
        $this->verifyCaptcha($data, $ip);

        $email = Email::sanitizeForDisplay(trim((string) ($data['email'] ?? '')));

        if ($email === '') {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException(__('legacy/recover.std_missing_email_address'));
        }

        if (! Email::isWellFormed($email)) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException(__('legacy/recover.std_invalid_email_address'));
        }

        $user = (array) DB::table('users')
            ->whereRaw('BINARY email = ?', [$email])
            ->first();

        if (empty($user)) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException(__('legacy/recover.std_email_not_in_database'));
        }

        if (($user['status'] ?? null) === UserStatus::PENDING->value) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException(__('legacy/recover.std_user_account_unconfirmed'));
        }

        $sec = Token::randomHex();

        $affected = User::query()->where('id', (int) $user['id'])->update(['editsecret' => $sec]);

        if (! $affected) {
            throw new AuthenticationException(__('legacy/recover.std_database_error'));
        }

        Cache::clearUser((int) $user['id'], '');

        // T-08/W1-04: Generate a CSPRNG recovery token and store its SHA-256 digest.
        // Legacy md5(editsecret + email + passhash + editsecret) path removed in W1-04.
        $recoveryToken = $this->tokenService->generate();
        $this->tokenService->store(self::RECOVERY_TOKEN_TABLE, $recoveryToken, [
            'user_id' => (int) $user['id'],
            'ip' => $ip,
        ]);

        // Send the secure token in the reset URL
        $this->sendResetRequestEmail($email, (int) $user['id'], $recoveryToken, $ip);
    }

    /**
     * Verify a password reset link and reset the user's password.
     */
    public function resetPassword(int $id, string $md5): string
    {
        // W1-04: Legacy md5 token path removed. Only SecureTokenService is accepted.
        $tokenRow = $this->tokenService->consume(self::RECOVERY_TOKEN_TABLE, $md5, [
            'consumed_at' => now()->toDateTimeString(),
        ]);

        if ($tokenRow === null) {
            throw new AuthenticationException(__('legacy/recover.std_unable_updating_user_data'));
        }

        // Verify user ID matches
        if ((int) $tokenRow['user_id'] !== $id) {
            throw new AuthenticationException(__('legacy/recover.std_unable_updating_user_data'));
        }

        $user = User::query()->find($id, ['id', 'username', 'email', 'passhash', 'editsecret']);
        if (! $user) {
            throw new AuthenticationException(__('legacy/recover.std_unable_updating_user_data'));
        }

        return $this->completePasswordReset($user);
    }

    /**
     * Complete the password reset: generate new password, update user, send email.
     */
    private function completePasswordReset(User $user): string
    {
        $id = (int) $user->id;
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
            throw new AuthenticationException(__('legacy/recover.std_unable_updating_user_data'));
        }

        Cache::clearUser($id, '');

        // T-24: Record password reset event in outbox
        $this->outboxService->recordPasswordReset(
            userId: $id,
            resetData: ['username' => $user->username],
        );

        $this->sendNewPasswordEmail($user, $newPassword);

        return $newPassword;
    }

    private function sendResetRequestEmail(string $email, int $userId, string $hash, string $ip): void
    {
        $baseUrl = SiteConfig::current()->basic->baseUrl();
        if (! str_contains($baseUrl, '://')) {
            $baseUrl = Http::protocolPrefix(Url::isSecure()).$baseUrl;
        }
        $baseUrl = rtrim($baseUrl, '/');
        $siteName = SiteConfig::current()->basic->siteName();

        $mailOne = __('legacy/recover.mail_one');
        $mailTwo = __('legacy/recover.mail_two');
        $mailThree = __('legacy/recover.mail_three');
        $mailFour = sprintf(__('legacy/recover.mail_four'), $siteName);
        $thisLink = __('legacy/recover.mail_this_link');

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
            $siteName.__('legacy/recover.mail_title'),
            $body,
            'confirmation',
            true,
            false,
            '',
            'UTF-8',
        );
    }

    private function sendNewPasswordEmail(User $user, string $newPassword): void
    {
        $baseUrl = SiteConfig::current()->basic->baseUrl();
        if (! str_contains($baseUrl, '://')) {
            $baseUrl = Http::protocolPrefix(Url::isSecure()).$baseUrl;
        }
        $baseUrl = rtrim($baseUrl, '/');
        $siteName = SiteConfig::current()->basic->siteName();

        $mailTwoFour = sprintf(__('legacy/recover.mail_two_four'), $siteName);

        $body = (__('legacy/recover.mail_two_one'))
            .(string) $user->username
            .(__('legacy/recover.mail_two_two'))
            .$newPassword
            .(__('legacy/recover.mail_two_three'))
            .'<b><a href="'.$baseUrl.'/login.php">'.(__('legacy/recover.mail_here')).'</a></b>'
            .$mailTwoFour;

        Mail::sentLegacy(
            (string) $user->email,
            $siteName,
            SiteConfig::current()->main->siteEmail(''),
            $siteName.__('legacy/recover.mail_two_title'),
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
     */
    private function verifyCaptcha(array $data, string $ip): void
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
            throw new AuthenticationException(__('legacy/functions.std_invalid_image_code'));
        }
    }
}
