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
use App\Support\Mail;
use App\Support\PasswordHasher;
use App\Support\Token;
use App\Support\Url;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles the password reset flow via SecureTokenService (CSPRNG + SHA-256 digest).
 */
class PasswordRecoveryService
{
    private const RECOVERY_TOKEN_TABLE = 'password_recovery_tokens';

    public function __construct(
        private WebAuthService $authService,
        private SecureTokenService $tokenService,
        private readonly PasswordSetup $passwordSetup,
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

            return;
        }

        if (($user['status'] ?? null) === UserStatus::PENDING->value) {
            $this->authService->recordFailedAttempt($ip);

            return;
        }

        $recoveryToken = $this->tokenService->generate();
        DB::transaction(function () use ($user, $recoveryToken, $ip): void {
            $this->revokeActiveTokens((int) $user['id']);
            $this->tokenService->store(self::RECOVERY_TOKEN_TABLE, $recoveryToken, [
                'user_id' => (int) $user['id'],
                'ip' => $ip,
            ]);
        });

        $this->sendResetRequestEmail($email, (int) $user['id'], $recoveryToken, $ip);
    }

    /**
     * Validate a reset link for display without consuming it.
     *
     * @return array<string, mixed>|null
     */
    public function validateResetToken(int $userId, string $token): ?array
    {
        if ($userId < 1 || $token === '') {
            return null;
        }

        $tokenRow = $this->tokenService->verify(self::RECOVERY_TOKEN_TABLE, $token);
        if ($tokenRow === null || (int) $tokenRow['user_id'] !== $userId) {
            return null;
        }

        $userExists = User::query()
            ->where('id', $userId)
            ->where('status', UserStatus::CONFIRMED->value)
            ->exists();

        return $userExists ? $tokenRow : null;
    }

    /**
     * Consume a reset token and set the user-selected password.
     */
    public function resetPassword(int $id, string $token, string $password, string $passwordConfirmation): void
    {
        $user = DB::transaction(function () use ($id, $token, $password, $passwordConfirmation): User {
            $tokenRow = $this->tokenService->consume(self::RECOVERY_TOKEN_TABLE, $token, [
                'consumed_at' => now()->toDateTimeString(),
            ]);

            if ($tokenRow === null || (int) $tokenRow['user_id'] !== $id) {
                throw new AuthenticationException(__('legacy/recover.std_invalid_reset_link'));
            }

            $user = User::query()
                ->where('id', $id)
                ->where('status', UserStatus::CONFIRMED->value)
                ->lockForUpdate()
                ->first(['id', 'username', 'email', 'status']);
            if (! $user instanceof User) {
                throw new AuthenticationException(__('legacy/recover.std_unable_updating_user_data'));
            }

            $this->passwordSetup->validate($password, $passwordConfirmation, (string) $user->username, 'recover');

            $affected = User::query()->where('id', $id)->update([
                'secret' => Token::randomHex(),
                'editsecret' => '',
                'passhash' => PasswordHasher::hash($password),
                'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
                'auth_key' => Token::randomHex(),
                'auth_version' => DB::raw('auth_version + 1'),
            ]);

            if (! $affected) {
                throw new AuthenticationException(__('legacy/recover.std_unable_updating_user_data'));
            }

            $this->revokeActiveTokens($id);
            $this->outboxService->recordPasswordReset(
                userId: $id,
                resetData: ['username' => $user->username],
            );

            return $user;
        });

        try {
            Cache::clearUser($id, '');
        } catch (Throwable $exception) {
            Log::warning('User cache clear failed after password reset', [
                'user_id' => $id,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->sendPasswordChangedEmail($user);
    }

    private function revokeActiveTokens(int $userId): void
    {
        DB::table(self::RECOVERY_TOKEN_TABLE)
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->where('revoked', 0)
            ->update(['revoked' => 1]);
    }

    private function sendResetRequestEmail(string $email, int $userId, string $hash, string $ip): void
    {
        $baseUrl = Url::siteBase();
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

        try {
            $sent = Mail::sentLegacy(
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
            if (! $sent) {
                Log::warning('Password reset request email was not sent', [
                    'user_id' => $userId,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Password reset request email failed', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendPasswordChangedEmail(User $user): void
    {
        $baseUrl = Url::siteBase();
        $siteName = SiteConfig::current()->basic->siteName();

        $body = (__('legacy/recover.mail_password_changed_one'))
            .htmlspecialchars((string) $user->username)
            .(__('legacy/recover.mail_password_changed_two'))
            .'<b><a href="'.$baseUrl.'/login.php">'.(__('legacy/recover.mail_here')).'</a></b>'
            .sprintf(__('legacy/recover.mail_password_changed_three'), $siteName);

        try {
            $sent = Mail::sentLegacy(
                (string) $user->email,
                $siteName,
                SiteConfig::current()->main->siteEmail(''),
                $siteName.__('legacy/recover.mail_password_changed_title'),
                $body,
                'details',
                true,
                false,
                '',
                'UTF-8',
            );
            if (! $sent) {
                Log::warning('Password changed notification was not sent', [
                    'user_id' => (int) $user->id,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Password changed notification failed', [
                'user_id' => (int) $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
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
        } catch (Throwable $exception) {
            $verified = false;
        }

        if (! $verified) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException(__('legacy/functions.std_invalid_image_code'));
        }
    }
}
