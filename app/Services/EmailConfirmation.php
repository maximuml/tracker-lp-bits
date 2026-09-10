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
     * @param  array<string, string>  $langConfirmResend
     * @param  array<string, string>  $langFunctions
     */
    public function resendConfirmation(array $data, string $ip, string $langFolder, array $langConfirmResend, array $langFunctions): string
    {
        if (SiteConfig::current()->main->verification('email') === 'admin') {
            throw new AuthenticationException($this->msg($langConfirmResend, 'std_need_admin_verification', 'Account needs manual verification from administrators.'));
        }

        $this->authService->assertNotBanned($ip);
        $this->verifyCaptcha($data, $ip, $langFunctions);

        $email = Email::sanitizeForDisplay(trim((string) ($data['email'] ?? '')));
        $password = trim((string) ($data['wantpassword'] ?? ''));
        $passAgain = trim((string) ($data['passagain'] ?? ''));

        if ($email === '' || $password === '' || $passAgain === '') {
            throw new AuthenticationException($this->msg($langConfirmResend, 'std_fields_blank', 'Don\'t leave any fields blank.'));
        }

        if (! Email::isWellFormed($email)) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException($this->msg($langConfirmResend, 'std_invalid_email_address', 'Invalid email address!'));
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException($this->msg($langConfirmResend, 'std_email_not_found', 'The email address was not found in the database.'));
        }

        if ($user->status !== UserStatus::PENDING) {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException($this->msg($langConfirmResend, 'std_user_already_confirm', 'User using this email address is already confirmed.'));
        }

        $this->passwordSetup->validate($password, $passAgain, (string) $user->username, $langConfirmResend);

        // W1-05: Rate-limit resend requests (max 5 per hour per IP)
        $this->assertResendRateLimit($ip, $langConfirmResend);

        $passwordData = $this->passwordSetup->forResend($password);

        $affected = User::query()->where('id', $user->id)->update([
            'passhash' => $passwordData['passhash'],
            'passhash_algo' => $passwordData['passhash_algo'],
            'secret' => $passwordData['secret'],
            'editsecret' => $passwordData['secret'],
        ]);

        if (! $affected) {
            throw new AuthenticationException($this->msg($langConfirmResend, 'std_database_error', 'Database error. Please contact an administrator about this.'));
        }

        Cache::clearUser($user->id, '');

        // W1-05: Revoke old confirmation tokens and generate a new secure one
        $this->revokeConfirmationTokens($user->id);
        $confirmToken = $this->generateConfirmationToken((int) $user->id, $ip);

        $this->sendConfirmationEmail((string) $user->username, $email, (int) $user->id, $confirmToken, $ip, $langFolder, $langConfirmResend);

        return 'ok.php?type=signup&email='.rawurlencode($email);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $langFunctions
     */
    public function verifyCaptcha(array $data, string $ip, array $langFunctions): void
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
     *
     * @param  array<string, string>  $langConfirmResend
     */
    private function assertResendRateLimit(string $ip, array $langConfirmResend): void
    {
        $cacheKey = "confirm_resend_rate:{$ip}";
        $count = (int) (CacheFacade::get($cacheKey, 0));

        if ($count >= self::RESEND_RATE_LIMIT) {
            throw new AuthenticationException($this->msg(
                $langConfirmResend,
                'std_rate_limited',
                'Too many confirmation email requests. Please try again later.',
            ));
        }

        CacheFacade::put($cacheKey, $count + 1, self::RESEND_RATE_LIMIT_TTL);
    }

    /**
     * @param  array<string, string>  $langMail
     */
    public function sendConfirmationEmail(
        string $username,
        string $email,
        int $userId,
        string $confirmToken,
        string $ip,
        string $langFolder,
        array $langMail,
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

        $mailOne = $langMail['mail_one'] ?? 'Hi ';
        $mailTwo = sprintf($langMail['mail_two'] ?? ',<br /><br />You have requested a new user account on %s and you have <br />specified this address ', $siteName);
        $mailThree = $langMail['mail_three'] ?? ' as user contact.<br /><br />If you did not do this, please ignore this email. The person who entered your <br />email address had the IP address ';
        $mailFour = $langMail['mail_four'] ?? '. Please do not reply.<br /><br />To confirm your user registration, you have to follow ';
        $mailFourOne = $langMail['mail_four_1'] ?? '<br /><br />If the Link above is broken or expired, try to send a new confirmation email again from ';
        $mailThisLink = $langMail['mail_this_link'] ?? 'THIS LINK';
        $mailHere = $langMail['mail_here'] ?? 'HERE';
        $mailFive = sprintf($langMail['mail_five'] ?? '', $siteName, $siteName, $reportEmail, $siteName);
        $title = $siteName.($langMail['mail_title'] ?? ' User Registration Confirmation');

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

    /**
     * @param  array<string, string>  $lang
     */
    private function msg(array $lang, string $key, string $fallback): string
    {
        return (string) ($lang[$key] ?? $fallback);
    }
}
