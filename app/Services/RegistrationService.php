<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Enums\UserClass as UserClassEnum;
use App\Enums\UserGender;
use App\Enums\UserStatus;
use App\Events\UserCreated;
use App\Exceptions\AuthenticationException;
use App\Models\Invite;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Email;
use App\Support\Http;
use App\Support\Locale;
use App\Support\Network;
use App\Support\Token;
use App\Support\Url;
use App\Support\Validators;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the user registration flow.
 */
class RegistrationService
{
    private const MAX_USERNAME_LENGTH = 12;

    public function __construct(
        private WebAuthService $authService,
        private EmailConfirmation $emailConfirmation,
        private InviteValidator $inviteValidator,
        private PasswordSetup $passwordSetup,
        private UserModerationRepositoryInterface $userModerationRepository,
        private OutboxService $outboxService,
    ) {}

    /**
     * Throw when registration is globally disabled, invite-only mismatch,
     * the IP is banned, max users reached, or max accounts per IP reached.
     *
     * @param  array<string, string>  $langSignup
     * @param  array<string, string>  $langFunctions
     */
    public function assertCanRegister(string $type, string $ip, array $langSignup, array $langFunctions): void
    {
        try {
            $this->authService->assertNotBanned($ip);
        } catch (AuthenticationException $exception) {
            throw new AuthenticationException($this->msg($langFunctions, 'std_your_ip_banned', $exception->getMessage()));
        }

        $isInvite = $type === 'invite';
        $isNormal = $type === 'normal';

        if ($isInvite && ! SiteConfig::current()->main->inviteSystem()) {
            throw new AuthenticationException($this->msg($langFunctions, 'std_invite_system_disabled', 'The invite system is currently disabled.'));
        }

        if ($isNormal && ! SiteConfig::current()->main->registration()) {
            throw new AuthenticationException($this->msg($langFunctions, 'std_open_registration_disabled', 'Open registration is currently disabled.'));
        }

        $maxUsers = (int) SiteConfig::current()->main->maxUsers(0);
        if ($maxUsers > 0 && User::query()->count() >= $maxUsers) {
            throw new AuthenticationException($this->msg($langFunctions, 'std_account_limit_reached', 'The current user account limit has been reached.'));
        }

        $maxIp = (int) SiteConfig::current()->security->maxIp(0);
        if ($maxIp > 0 && User::query()->where('ip', $ip)->count() > $maxIp) {
            throw new AuthenticationException(
                $this->msg($langFunctions, 'std_the_ip', 'The IP ')
                .'<b>'.htmlspecialchars($ip).'</b>'
                .sprintf($this->msg($langFunctions, 'std_used_many_times', ' is already being used on too many accounts. No more accounts allowed at <b>%s</b>.'), SiteConfig::current()->basic->siteName())
            );
        }
    }

    /**
     * Register a new user. Returns the created user and the success redirect URL.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $langSignup
     * @param  array<string, string>  $langTakesignup
     * @param  array<string, string>  $langFunctions
     * @return array{user: User, redirect: string}
     */
    public function signup(array $data, string $ip, string $langFolder, array $langSignup, array $langTakesignup, array $langFunctions): array
    {
        $type = ($data['type'] ?? '') === 'invite' ? 'invite' : 'normal';
        $this->assertCanRegister($type, $ip, $langSignup, $langFunctions);

        $this->emailConfirmation->verifyCaptcha($data, $ip, $langFunctions);

        $isInvite = $type === 'invite';
        $code = $isInvite ? trim((string) ($data['hash'] ?? '')) : '';
        $inviter = $isInvite ? (int) ($data['inviter'] ?? 0) : 0;
        $invite = $isInvite ? $this->inviteValidator->validate($code, $inviter, $langSignup, $langFolder) : null;

        $isPreRegister = SiteConfig::current()->system->isInvitePreEmailAndUsername();

        if ($isInvite && $isPreRegister && ! empty($invite->pre_register_username) && ! empty($invite->pre_register_email)) {
            $username = (string) $invite->pre_register_username;
            $email = (string) $invite->pre_register_email;
            $passwordInput = trim((string) ($data['wantpassword'] ?? ''));
        } else {
            $username = trim((string) ($data['wantusername'] ?? ''));
            $email = trim((string) ($data['email'] ?? ''));
            $passwordInput = trim((string) ($data['wantpassword'] ?? ''));
        }

        $email = Email::sanitizeForDisplay($email);
        $country = (int) ($data['country'] ?? 0);
        $gender = ucfirst(strtolower(trim((string) ($data['gender'] ?? ''))));
        $passwordAgain = trim((string) ($data['passagain'] ?? ''));

        $this->validateSignupFields(
            $username,
            $email,
            $passwordInput,
            $passwordAgain,
            $gender,
            $country,
            $isInvite && $isPreRegister && $invite !== null,
            $langSignup,
            $langTakesignup,
        );

        $rulesVerify = ($data['rulesverify'] ?? '') === 'yes';
        $faqVerify = ($data['faqverify'] ?? '') === 'yes';
        $ageVerify = ($data['ageverify'] ?? '') === 'yes';
        if (! $rulesVerify || ! $faqVerify || ! $ageVerify) {
            throw new AuthenticationException($this->msg($langTakesignup, 'std_unqualified', 'Sorry, you are not qualified to become a member of this site.'));
        }

        if (User::query()->where('username', $username)->exists()) {
            throw new AuthenticationException($this->msg($langTakesignup, 'std_username_exists', 'Username already exists!'));
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new AuthenticationException(
                $this->msg($langTakesignup, 'std_email_address', 'The e-mail address ')
                .$email
                .$this->msg($langTakesignup, 'std_in_use', ' is already in use.')
            );
        }

        $passwordData = $this->passwordSetup->forNewUser($passwordInput);
        $authKey = Token::randomHex();
        $verification = (string) SiteConfig::current()->main->verification('email');

        $userData = [
            'username' => $username,
            'passhash' => $passwordData['passhash'],
            'passhash_algo' => $passwordData['passhash_algo'],
            'passkey' => $passwordData['passkey'],
            'secret' => $passwordData['secret'],
            'auth_key' => $authKey,
            'editsecret' => $verification === 'admin' ? '' : $passwordData['secret'],
            'email' => $email,
            'country' => $country,
            'gender' => UserGender::fromStringSafe($gender)->value,
            'status' => UserStatus::PENDING->value,
            'class' => SiteConfig::current()->authority->defaultClass((int) UserClassEnum::USER->value),
            'invites' => (int) SiteConfig::current()->main->inviteCount(0),
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'lang' => Locale::idFromFolder($langFolder),
            'stylesheet' => (int) SiteConfig::current()->main->defStylesheet(1),
            'uploaded' => max(0, (int) SiteConfig::current()->main->iniUpload(0)),
            'ip' => $ip,
        ];

        if ($isInvite && $invite !== null) {
            $userData['invited_by'] = (int) $invite->inviter;
        }

        $id = User::query()->insertGetId($userData);

        $user = User::query()->findOrFail($id);
        $user->makeVisible(['secret']);

        event(new UserCreated($user));

        // T-24: Record user registered event in outbox
        $this->outboxService->recordUserRegistered(
            userId: (int) $user->id,
            userData: [
                'username' => $user->username,
                'email' => $user->email,
                'class' => $user->class,
                'invited_by' => $isInvite && $invite !== null ? (int) $invite->inviter : null,
            ],
        );

        $this->sendWelcomeMessage($user, $langTakesignup);
        $this->maybeAddTemporaryInvite($id);

        if ($isInvite && $invite !== null) {
            $this->inviteValidator->consume($invite, $id, $email, $username);
        }

        // W1-05: Generate a secure confirmation token (CSPRNG + SHA-256 digest)
        $confirmToken = $this->emailConfirmation->generateConfirmationToken($id, $ip);

        $redirect = $this->resolveSignupRedirect($id, $confirmToken, $user, $verification, $email, $langFolder, $langTakesignup);

        return ['user' => $user, 'redirect' => $redirect];
    }

    /**
     * Confirm a pending account from a confirmation link.
     */
    public function confirm(int $id, string $confirmToken, string $ip): User
    {
        return $this->emailConfirmation->confirm($id, $confirmToken, $ip);
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
        return $this->emailConfirmation->resendConfirmation($data, $ip, $langFolder, $langConfirmResend, $langFunctions);
    }

    /**
     * @param  array<string, string>  $langTakesignup
     */
    private function sendWelcomeMessage(User $user, array $langTakesignup): void
    {
        $subject = $this->msg($langTakesignup, 'msg_subject', 'Welcome to ').SiteConfig::current()->basic->siteName().'!';
        $msg = MessageTemplate::forRegisterWelcome($user->lang, ['username' => $user->username]);

        if (empty($msg)) {
            $msg = $this->msg($langTakesignup, 'msg_congratulations', 'Congratulations ')
                .$user->username
                .sprintf($this->msg($langTakesignup, 'msg_you_are_a_member', ''), SiteConfig::current()->basic->siteName(), SiteConfig::current()->basic->siteName());
        }

        Message::add([
            'sender' => null,
            'receiver' => $user->id,
            'subject' => $subject,
            'added' => now()->toDateTimeString(),
            'msg' => $msg,
        ]);
    }

    private function maybeAddTemporaryInvite(int $userId): void
    {
        $tmpInviteCount = (int) SiteConfig::current()->main->tmpInviteCount(0);
        if ($tmpInviteCount <= 0) {
            return;
        }

        $this->userModerationRepository->addTemporaryInvite(null, $userId, 'increment', $tmpInviteCount, 7);
    }

    /**
     * @param  array<string, string>  $langTakesignup
     */
    private function resolveSignupRedirect(int $userId, string $confirmToken, User $user, string $verification, string $email, string $langFolder, array $langTakesignup): string
    {
        $baseUrl = SiteConfig::current()->basic->baseUrl();
        if (! str_contains($baseUrl, '://')) {
            $baseUrl = Http::protocolPrefix(Url::isSecure()).$baseUrl;
        }
        $baseUrl = rtrim($baseUrl, '/');
        $type = $user->invited_by ? 'invite' : 'normal';

        if ($verification === 'admin') {
            return $type === 'invite'
                ? 'ok.php?type=inviter'
                : 'ok.php?type=adminactivate';
        }

        if ($verification === 'automatic' || SiteConfig::current()->smtp->type('none') === 'none') {
            return $baseUrl.'/confirm.php?id='.$userId.'&secret='.$confirmToken;
        }

        $this->emailConfirmation->sendConfirmationEmail((string) $user->username, $email, $userId, $confirmToken, Network::clientIp(), $langFolder, $langTakesignup);

        return 'ok.php?type=signup&email='.rawurlencode($email);
    }

    /**
     * @param  array<string, string>  $langSignup
     * @param  array<string, string>  $langTakesignup
     */
    private function validateSignupFields(
        string $username,
        string $email,
        string $password,
        string $passAgain,
        string $gender,
        int $country,
        bool $preRegistered,
        array $langSignup,
        array $langTakesignup,
    ): void {
        if (! $preRegistered && ($username === '' || $password === '' || $email === '' || $country === 0 || $gender === '')) {
            throw new AuthenticationException($this->msg($langTakesignup, 'std_blank_field', 'Don\'t leave any fields blank.'));
        }

        if (strlen($username) > self::MAX_USERNAME_LENGTH) {
            throw new AuthenticationException($this->msg($langTakesignup, 'std_username_too_long', 'Sorry, username is too long (max is 12 chars).'));
        }

        if (! $preRegistered && ! Validators::isUsername($username)) {
            throw new AuthenticationException($this->msg($langTakesignup, 'std_invalid_username', 'Invalid username.'));
        }

        if (! Email::isWellFormed($email)) {
            throw new AuthenticationException($this->msg($langTakesignup, 'std_wrong_email_address_format', 'That doesn\'t look like a valid email address.'));
        }

        $this->passwordSetup->validate($password, $passAgain, $username, $langTakesignup);

        $allowedGenders = [UserGender::MALE->stringValue(), UserGender::FEMALE->stringValue()];
        if (! in_array($gender, $allowedGenders, true)) {
            throw new AuthenticationException($this->msg($langTakesignup, 'std_invalid_gender', 'Invalid Gender!'));
        }

        if (DB::table('countries')->where('id', $country)->doesntExist()) {
            throw new AuthenticationException($this->msg($langTakesignup, 'std_invalid_gender', 'Invalid country.'));
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
