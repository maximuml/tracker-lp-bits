<?php

namespace App\Services;

use App\Enums\ModelEventEnum;
use App\Exceptions\AuthenticationException;
use App\Models\Invite;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\AuthCookie;
use App\Support\Cache;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\Email;
use App\Support\Events;
use App\Support\Http;
use App\Support\Locale;
use App\Support\Mail;
use App\Support\Network;
use App\Support\PasswordHasher;
use App\Support\Strings;
use App\Support\Token;
use App\Support\Url;
use App\Support\Validators;
use Illuminate\Support\Facades\DB;

/**
 * Handles user registration, account confirmation, and confirmation-resend flows.
 */
class RegistrationService
{
    private const MAX_USERNAME_LENGTH = 12;

    private const MIN_PASSWORD_LENGTH = 6;

    private const MAX_PASSWORD_LENGTH = 40;

    public function __construct(
        private WebAuthService $authService,
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

        $this->verifyCaptcha($data, $ip, $langFunctions);

        $isInvite = $type === 'invite';
        $code = $isInvite ? trim((string) ($data['hash'] ?? '')) : '';
        $inviter = $isInvite ? (int) ($data['inviter'] ?? 0) : 0;
        $invite = null;

        if ($isInvite) {
            if ($code === '') {
                throw new AuthenticationException(
                    $this->msg($langSignup, 'std_error', 'Error').': '.$this->msg($langSignup, 'std_uninvited', 'Require invitation number.')
                );
            }

            $invite = Invite::query()
                ->where('hash', $code)
                ->where('valid', Invite::VALID_YES)
                ->first();

            if (! $invite) {
                throw new AuthenticationException($this->msg($langSignup, 'std_uninvited', 'Incorrect invitation code.'));
            }

            if ((int) $invite->inviter !== $inviter) {
                Invite::query()->where('id', $invite->id)->update(['valid' => Invite::VALID_NO]);
                throw new AuthenticationException(Locale::trans('invite.invalid_inviter', [], $langFolder));
            }
        }

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
        $isClientHashed = ($data['wantpassword_hashed'] ?? '0') === '1';

        $this->validateSignupFields(
            $username,
            $email,
            $passwordInput,
            $passwordAgain,
            $gender,
            $country,
            $isInvite && $isPreRegister && $invite !== null,
            $isClientHashed,
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

        // Use argon2id for new passwords. If the client pre-hashed the password
        // (isClientHashed, legacy), we fall back to legacy sha256 since we don't have
        // the plaintext password to feed to password_hash(). New clients send
        // plaintext so argon2id is always used.
        if ($isClientHashed) {
            $secret = Token::randomHex();
            $passhash = hash('sha256', $secret.$passwordInput);
            $passhashAlgo = PasswordHasher::ALGO_SHA256;
        } else {
            $passhash = PasswordHasher::hash($passwordInput);
            $secret = Token::randomHex();
            $passhashAlgo = PasswordHasher::ALGO_ARGON2ID;
        }
        $authKey = Token::randomHex();
        $passkey = md5($username.now()->toDateTimeString().$passhash);
        $verification = (string) SiteConfig::current()->main->verification('email');
        $editsecret = $verification === 'admin' ? '' : $secret;

        $userData = [
            'username' => $username,
            'passhash' => $passhash,
            'passhash_algo' => $passhashAlgo,
            'passkey' => $passkey,
            'secret' => $secret,
            'auth_key' => $authKey,
            'editsecret' => $editsecret,
            'email' => $email,
            'country' => $country,
            'gender' => $gender,
            'status' => 'pending',
            'class' => SiteConfig::current()->authority->defaultClass((int) User::CLASS_USER),
            'invites' => (int) SiteConfig::current()->main->inviteCount(0),
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'lang' => Locale::idFromFolder($langFolder),
            'stylesheet' => (int) SiteConfig::current()->main->defStylesheet(1),
            'uploaded' => max(0, (int) SiteConfig::current()->main->iniUpload(0)),
            'ip' => $ip,
        ];

        if ($isInvite) {
            $userData['invited_by'] = (int) $invite->inviter;
        }

        $id = User::query()->insertGetId($userData);

        $user = User::query()->findOrFail($id);
        $user->makeVisible(['secret']);

        Events::fire(ModelEventEnum::USER_CREATED, $user, null);

        $this->sendWelcomeMessage($user, $langTakesignup);
        $this->maybeAddTemporaryInvite($id);

        if ($isInvite) {
            $this->consumeInvite($invite, $id, $email, $username);
        }

        $redirect = $this->resolveSignupRedirect($id, $secret, $user, $verification, $email, $langFolder, $langTakesignup);

        return ['user' => $user, 'redirect' => $redirect];
    }

    /**
     * Confirm a pending account from a confirmation link.
     */
    public function confirm(int $id, string $confirmMd5, string $ip): User
    {
        $user = User::query()->find($id, ['id', 'passhash', 'secret', 'auth_key', 'editsecret', 'status', 'username']);

        if (! $user) {
            abort(404);
        }

        if ($user->status === 'confirmed') {
            return $user;
        }

        if ($user->status !== 'pending') {
            abort(404);
        }

        $user->makeVisible(['secret']);

        if (md5(Strings::padHash($user->secret)) !== $confirmMd5) {
            abort(404);
        }

        $affected = User::query()->where('id', $id)->where('status', 'pending')->update([
            'status' => 'confirmed',
            'editsecret' => '',
        ]);

        if (! $affected) {
            abort(404);
        }

        $user->refresh();

        Events::fire(ModelEventEnum::USER_UPDATED, $user, null);
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

        if ($user->status !== 'pending') {
            $this->authService->recordFailedAttempt($ip);
            throw new AuthenticationException($this->msg($langConfirmResend, 'std_user_already_confirm', 'User using this email address is already confirmed.'));
        }

        $this->validatePassword($password, $passAgain, (string) $user->username, $langConfirmResend);

        $secret = Token::randomHex();
        $passhash = PasswordHasher::hash($password);
        $verification = (string) SiteConfig::current()->main->verification('email');
        $editsecret = $verification === 'admin' ? '' : $secret;

        $affected = User::query()->where('id', $user->id)->update([
            'passhash' => $passhash,
            'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
            'secret' => $secret,
            'editsecret' => $editsecret,
        ]);

        if (! $affected) {
            throw new AuthenticationException($this->msg($langConfirmResend, 'std_database_error', 'Database error. Please contact an administrator about this.'));
        }

        Cache::clearUser($user->id, '');

        $this->sendConfirmationEmail((string) $user->username, $email, $user->id, $editsecret, $ip, $langFolder, $langConfirmResend);

        return 'ok.php?type=signup&email='.rawurlencode($email);
    }

    /**
     * @param  array<string, string>  $langFunctions
     * @param  array<string, mixed>  $data
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
        bool $isClientHashed,
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

        if (! $isClientHashed) {
            $this->validatePassword($password, $passAgain, $username, $langTakesignup);
        }

        $allowedGenders = ['Male', 'Female'];
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
    private function validatePassword(string $password, string $passAgain, string $username, array $lang): void
    {
        if ($password !== $passAgain) {
            throw new AuthenticationException($this->msg($lang, 'std_passwords_unmatched', 'The passwords didn\'t match!'));
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new AuthenticationException($this->msg($lang, 'std_password_too_short', 'Sorry, password is too short (min is 6 chars).'));
        }

        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            throw new AuthenticationException($this->msg($lang, 'std_password_too_long', 'Sorry, password is too long (max is 40 chars).'));
        }

        if ($password === $username) {
            throw new AuthenticationException($this->msg($lang, 'std_password_equals_username', 'Sorry, password cannot be same as user name.'));
        }
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
            'sender' => 0,
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

        (new UserRepository)->addTemporaryInvite(null, $userId, 'increment', $tmpInviteCount, 7);
    }

    private function consumeInvite(Invite $invite, int $userId, string $email, string $username): void
    {
        Invite::query()->where('id', $invite->id)->update([
            'valid' => Invite::VALID_NO,
            'invitee_register_uid' => $userId,
            'invitee_register_email' => $email,
            'invitee_register_username' => $username,
        ]);

        $inviter = (int) $invite->inviter;
        $locale = Locale::userLocale($inviter);
        $subject = Locale::trans('user.msg_invited_user_has_registered', [], $locale);
        $msg = Locale::trans('user.msg_user_you_invited', [], $locale)
            .$username
            .Locale::trans('user.msg_has_registered', [], $locale);

        Message::add([
            'sender' => 0,
            'receiver' => $inviter,
            'subject' => $subject,
            'added' => now()->toDateTimeString(),
            'msg' => $msg,
        ]);

        Cache::clearUser($inviter, '');
    }

    /**
     * @param  array<string, string>  $langTakesignup
     */
    private function resolveSignupRedirect(int $userId, string $secret, User $user, string $verification, string $email, string $langFolder, array $langTakesignup): string
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
            $psecret = md5(Strings::padHash($secret));

            return $baseUrl.'/confirm.php?id='.$userId.'&secret='.$psecret;
        }

        $this->sendConfirmationEmail((string) $user->username, $email, $userId, $secret, Network::clientIp(), $langFolder, $langTakesignup);

        return 'ok.php?type=signup&email='.rawurlencode($email);
    }

    /**
     * @param  array<string, string>  $langMail  Either $lang_takesignup or $lang_confirm_resend
     */
    private function sendConfirmationEmail(
        string $username,
        string $email,
        int $userId,
        string $secret,
        string $ip,
        string $langFolder,
        array $langMail,
    ): void {
        $baseUrl = SiteConfig::current()->basic->baseUrl();
        if (! str_contains($baseUrl, '://')) {
            $baseUrl = Http::protocolPrefix(Url::isSecure()).$baseUrl;
        }
        $baseUrl = rtrim($baseUrl, '/');
        $psecret = md5(Strings::padHash($secret));
        $confirmUrl = $baseUrl.'/confirm.php?id='.$userId.'&secret='.$psecret;
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
