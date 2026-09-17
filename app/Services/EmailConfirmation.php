<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Events\UserUpdated;
use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Support\AuthCookie;
use App\Support\Cache;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\Email;
use App\Support\Http;
use App\Support\Mail;
use App\Support\Url;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;

/**
 * Handles account confirmation and confirmation-resend flows.
 */
class EmailConfirmation
{
    private const CONFIRMATION_TOKEN_TABLE = 'email_confirmation_tokens';

    private const RESEND_RATE_LIMIT = 5;

    private const RESEND_RATE_LIMIT_TTL = 3600;

    public function __construct(
        private readonly WebAuthService $authService,
        private readonly PasswordSetup $passwordSetup,
        private readonly SecureTokenService $tokenService,
    ) {}

    /**
     * Confirm a pending account from a confirmation link.
     */
    public function confirm(int $id, string $confirmToken, string $ip): User
    {
        $user = User::query()->find($id, ['id', 'passhash', 'secret', 'auth_key', 'editsecret', 'status', 'username']);

        if (! $user) {
            abort(404);
        }

        if ($user->status === UserStatus::CONFIRMED) {
            return $user;
        }

        if ($user->status !== UserStatus::PENDING) {
            abort(404);
        }

        // W1-05: Verify token via SecureTokenService (atomic consumption)
        $tokenRow = $this->tokenService->consume(self::CONFIRMATION_TOKEN_TABLE, $confirmToken, [
            'consumed_at' => now()->toDateTimeString(),
        ]);

        if ($tokenRow === null || (int) $tokenRow['user_id'] !== $id) {
            abort(404);
        }

        $affected = User::query()->where('id', $id)->where('status', UserStatus::PENDING->value)->update([
            'status' => UserStatus::CONFIRMED->value,
            'editsecret' => '',
        ]);

        if (! $affected) {
            abort(404);
        }

        $user->refresh();

        event(new UserUpdated($user));
        Cache::clearUser($id, '');
        AuthCookie::setLoginCookie($id);

        return $user;
    }

    /**
     * Re-send a confirmation email for a pending account.
     *
     * @param  array<string, mixed>  $data
     */
    public function resendConfirmation(array $data, string $ip, string $langFolder): string
    {
        if (SiteConfig::current()->main->verification('email') === 'admin') {
            throw new AuthenticationException(__('legacy/confirm_resend.std_need_admin_verification'));
        }

        $this->authService->assertNotBanned($ip);
        $this->verifyCaptcha($data, $ip);

        $email = Email::sanitizeForDisplay(trim((string) ($data['email'] ?? '')));
        $password = trim((string) ($data['wantpassword'] ?? ''));
        $passAgain = trim((string) ($data['passagain'] ?? ''));

        if ($email === '' || $password === '' || $passAgain === '') {
            throw new AuthenticationException(__('legacy/confirm_resend.std_fields_blank'));
        }

        if (! Email::isWellFormed($email)) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException(__('legacy/confirm_resend.std_invalid_email_address'));
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException(__('legacy/confirm_resend.std_email_not_found'));
        }

        if ($user->status !== UserStatus::PENDING) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException(__('legacy/confirm_resend.std_user_already_confirm'));
        }

        $this->passwordSetup->validate($password, $passAgain, (string) $user->username, 'confirm_resend');

        // W1-05: Rate-limit resend requests (max 5 per hour per IP)
        $this->assertResendRateLimit($ip);

        $passwordData = $this->passwordSetup->forResend($password);

        $affected = User::query()->where('id', $user->id)->update([
            'passhash' => $passwordData['passhash'],
            'passhash_algo' => $passwordData['passhash_algo'],
            'secret' => $passwordData['secret'],
            'editsecret' => $passwordData['secret'],
        ]);

        if (! $affected) {
            throw new AuthenticationException(__('legacy/confirm_resend.std_database_error'));
        }

        Cache::clearUser($user->id, '');

        // W1-05: Revoke old confirmation tokens and generate a new secure one
        $this->revokeConfirmationTokens($user->id);
        $confirmToken = $this->generateConfirmationToken((int) $user->id, $ip);

        $this->sendConfirmationEmail((string) $user->username, $email, (int) $user->id, $confirmToken, $ip, $langFolder);

        return 'ok.php?type=signup&email='.rawurlencode($email);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function verifyCaptcha(array $data, string $ip): void
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

    /**
     * W1-05: Generate a secure confirmation token and store its digest.
     */
    public function generateConfirmationToken(int $userId, string $ip): string
    {
        $token = $this->tokenService->generate();
        $this->tokenService->store(self::CONFIRMATION_TOKEN_TABLE, $token, [
            'user_id' => $userId,
            'ip' => $ip,
        ]);

        return $token;
    }

    /**
     * W1-05: Revoke all existing confirmation tokens for a user.
     */
    private function revokeConfirmationTokens(int $userId): void
    {
        DB::table(self::CONFIRMATION_TOKEN_TABLE)
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->update(['revoked' => 1]);
    }

    /**
     * W1-05: Rate-limit resend requests per IP (max 5 per hour).
     */
    private function assertResendRateLimit(string $ip): void
    {
        $cacheKey = "confirm_resend_rate:{$ip}";
        $count = (int) (CacheFacade::get($cacheKey, 0));

        if ($count >= self::RESEND_RATE_LIMIT) {
            throw new AuthenticationException(('Too many confirmation email requests. Please try again later.'));
        }

        CacheFacade::put($cacheKey, $count + 1, self::RESEND_RATE_LIMIT_TTL);
    }

    public function sendConfirmationEmail(
        string $username,
        string $email,
        int $userId,
        string $confirmToken,
        string $ip,
        string $langFolder,
    ): void {
        $baseUrl = SiteConfig::current()->basic->baseUrl();
        if (! str_contains($baseUrl, '://')) {
            $baseUrl = Http::protocolPrefix(Url::isSecure()).$baseUrl;
        }
        $baseUrl = rtrim($baseUrl, '/');
        $confirmUrl = $baseUrl.'/confirm.php?id='.$userId.'&secret='.$confirmToken;
        $resendUrl = $baseUrl.'/confirm_resend.php';
        $siteName = SiteConfig::current()->basic->siteName();
        $reportEmail = SiteConfig::current()->main->reportEmail('');

        $mailOne = __('legacy/confirm_resend.mail_one');
        $mailTwo = sprintf(__('legacy/confirm_resend.mail_two'), $siteName);
        $mailThree = __('legacy/confirm_resend.mail_three');
        $mailFour = __('legacy/confirm_resend.mail_four');
        $mailFourOne = __('legacy/confirm_resend.mail_four_1');
        $mailThisLink = __('legacy/confirm_resend.mail_this_link');
        $mailHere = __('legacy/confirm_resend.mail_here');
        $mailFive = sprintf(__('legacy/confirm_resend.mail_five'), $siteName, $siteName, $reportEmail, $siteName);
        $title = $siteName.(__('legacy/confirm_resend.mail_title'));

        $body = $mailOne
            .htmlspecialchars($username)
            .$mailTwo
            .'('.htmlspecialchars($email).')'
            .$mailThree
            .htmlspecialchars($ip)
            .$mailFour
            .'<b><a href="javascript:void(null)" onclick="window.open(\''.$confirmUrl.'\')">'
            .$mailThisLink
            .'</a></b><br />'
            .$confirmUrl
            .$mailFourOne
            .'<b><a href="javascript:void(null)" onclick="window.open(\''.$resendUrl.'\')">'.$mailHere.'</a></b><br />'
            .$resendUrl
            .'<br />'
            .$mailFive;

        Mail::sentLegacy(
            $email,
            $siteName,
            SiteConfig::current()->main->siteEmail(''),
            $title,
            $body,
            'signup',
            false,
            false,
            '',
            'UTF-8',
        );
    }
}
