<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Repositories\UserAccountRepository;
use App\Repositories\UserDetailRepository;
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
        private UserDetailRepository $userDetailRepository,
        private WebAuthService $authService,
        private SecureTokenService $tokenService,
        private readonly PasswordSetup $passwordSetup,
        private readonly UserAccountRepository $userAccountRepository,
        private readonly OutboxService $outboxService,
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
            throw new AuthenticationException(__('recover.std_missing_email_address'));
        }

        if (! Email::isWellFormed($email)) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException(__('recover.std_invalid_email_address'));
        }

        $user = $this->userDetailRepository->findByEmailBinary($email)?->getAttributes() ?? [];

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

        $userExists = $this->userAccountRepository->existsConfirmedById($userId);

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
                throw new AuthenticationException(__('recover.std_invalid_reset_link'));
            }

            $user = $this->userAccountRepository->findConfirmedById($id, ['id', 'username', 'email', 'status'], true);
            if (! $user instanceof User) {
                throw new AuthenticationException(__('recover.std_unable_updating_user_data'));
            }

            $this->passwordSetup->validate($password, $passwordConfirmation, (string) $user->username, 'recover');

            $affected = $this->userAccountRepository->updateById($id, [
                'secret' => Token::randomHex(),
                'editsecret' => '',
                'passhash' => PasswordHasher::hash($password),
                'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
                'auth_key' => Token::randomHex(),
                'auth_version' => DB::raw('auth_version + 1'),
            ]);

            if (! $affected) {
                throw new AuthenticationException(__('recover.std_unable_updating_user_data'));
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
        $this->tokenService->revokeUnconsumed(self::RECOVERY_TOKEN_TABLE, $userId);
    }

    private function sendResetRequestEmail(string $email, int $userId, string $hash, string $ip): void
    {
        $baseUrl = Url::siteBase();
        $siteName = SiteConfig::current()->basic->siteName();

        $resetUrl = $baseUrl.'/recover?id='.$userId.'&secret='.$hash;

        $body = view('emails.password-reset', [
            'email' => $email,
            'ip' => $ip,
            'resetUrl' => $resetUrl,
            'siteName' => $siteName,
        ])->render();

        try {
            $sent = Mail::queueLegacy(
                $email,
                $siteName,
                SiteConfig::current()->main->siteEmail(''),
                $siteName.__('recover.mail_title'),
                nl2br($body),
                'confirmation',
                true,
                false,
                '',
                'UTF-8',
            );
            if (! $sent) {
                Log::warning('Password reset request email was not queued', [
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

        $body = view('emails.password-changed', [
            'username' => (string) $user->username,
            'loginUrl' => $baseUrl.'/login',
            'siteName' => $siteName,
        ])->render();

        try {
            $sent = Mail::queueLegacy(
                (string) $user->email,
                $siteName,
                SiteConfig::current()->main->siteEmail(''),
                $siteName.__('recover.mail_password_changed_title'),
                nl2br($body),
                'details',
                true,
                false,
                '',
                'UTF-8',
            );
            if (! $sent) {
                Log::warning('Password changed notification was not queued', [
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
            throw new AuthenticationException(__('functions.std_invalid_image_code'));
        }
    }
}
