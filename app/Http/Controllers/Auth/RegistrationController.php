<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Contracts\Repositories\UsercpLookupRepositoryInterface;
use App\Enums\UserStatus;
use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmResendRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Models\Setting;
use App\Repositories\InviteRepository;
use App\Services\RegistrationService;
use App\Services\WebAuthService;
use App\Support\AssetAppender;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\Support\Network;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function __construct(private readonly UsercpLookupRepositoryInterface $usercpLookupRepository, private readonly InviteRepository $inviteRepository,
        private RegistrationService $registrationService,
        private WebAuthService $authService,
    ) {}

    public function showSignup(Request $request): View|RedirectResponse
    {
        if (Auth::guard('nexus-web')->check()) {
            return Redirect::to('/web/index');
        }

        $langFolder = $this->resolveLangFolder($request);

        $sitelanguage = (int) $request->query('sitelanguage', 0);
        if ($sitelanguage > 0) {
            $folder = Locale::folderForId($sitelanguage, $langFolder);
            if ($folder !== '') {
                Locale::setFolderCookie($folder);
                $query = $request->query();
                unset($query['sitelanguage']);

                return Redirect::to('/signup'.(empty($query) ? '' : '?'.http_build_query($query)));
            }
        }

        $type = (string) $request->query('type', '');
        $isInvite = $type === 'invite';
        $code = trim((string) ($request->query('invitenumber', $request->query('hash', ''))));
        $secret = (string) $request->query('secret', '');

        $invite = null;
        if ($isInvite && $code !== '') {
            $invite = $this->inviteRepository->findValidByHash($code);
        }

        $captchaEnabled = $this->authService->isCaptchaEnabled();
        $captchaMarkup = '';

        if ($captchaEnabled) {
            $captchaMarkup = Captcha::renderHtml('yes', $secret, 'grid');
        }

        $countries = $this->usercpLookupRepository->getCountryOptions();

        $isPreRegister = SiteConfig::current()->system->isInvitePreEmailAndUsername();
        $preUsername = $isInvite && $isPreRegister && ! empty($invite->pre_register_username)
            ? (string) $invite->pre_register_username
            : '';
        $preEmail = $isInvite && $isPreRegister && ! empty($invite->pre_register_email)
            ? (string) $invite->pre_register_email
            : '';

        // Inline style= is CSP-blocked (style-src has no unsafe-inline) —
        // sizing comes from the nx-field__input class in modern.css.
        $oldUsername = old('wantusername');
        $oldEmail = old('email');
        $usernameValue = $preUsername !== '' ? $preUsername : (is_string($oldUsername) ? $oldUsername : '');
        $emailValue = $preEmail !== '' ? $preEmail : (is_string($oldEmail) ? $oldEmail : '');

        AssetAppender::js('js/auth-form.js', 'footer', true, 'auth-form');

        return view('auth.signup', [
            'langFolder' => $langFolder,
            'languages' => Locale::languageList('site_lang', true),
            'captchaEnabled' => $captchaEnabled,
            'captchaMarkup' => SafeHtml::fromTrustedHtml($captchaMarkup),
            'secret' => $secret,
            'type' => $type,
            'isInvite' => $isInvite,
            'invite' => $invite,
            'code' => $code,
            'countries' => $countries,
            'remaining' => $this->authService->remainingAttempts(Network::clientIp()),
            'maxAttempts' => $this->authService->maxLoginAttempts(),
            'error' => $request->session()->get('error'),
            'siteName' => Setting::getSiteName(),
            'headTitle' => $isInvite
                ? (__('signup.head_invite_signup'))
                : (__('signup.head_signup')),
            'usernameValue' => $usernameValue,
            'usernameReadonly' => $preUsername !== '',
            'emailValue' => $emailValue,
            'emailReadonly' => $preEmail !== '',
        ]);
    }

    public function signup(SignupRequest $request): RedirectResponse
    {
        if (Auth::guard('nexus-web')->check()) {
            return Redirect::to('/web/index');
        }

        $langFolder = $this->resolveLangFolder($request);

        try {
            $result = $this->registrationService->signup(
                $request->validated(),
                Network::clientIp(),
                $langFolder,
            );
        } catch (AuthenticationException $exception) {
            return $this->backWithError($request, $exception->getMessage());
        }

        return Redirect::to($result['redirect']);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $id = (int) $request->query('id', 0);
        $secret = (string) $request->query('secret', '');

        try {
            $user = $this->registrationService->confirm($id, $secret, Network::clientIp());
        } catch (AuthenticationException $exception) {
            return Redirect::to('/web/ok?type=confirmed');
        }

        if ($user->status !== UserStatus::PENDING && $user->status !== UserStatus::CONFIRMED) {
            return Redirect::to('/web/ok?type=confirmed');
        }

        return Redirect::to('/web/ok?type=confirm');
    }

    public function showConfirmResend(Request $request): View|RedirectResponse
    {
        if (Auth::guard('nexus-web')->check()) {
            return Redirect::to('/web/index');
        }

        $langFolder = $this->resolveLangFolder($request);

        $sitelanguage = (int) $request->query('sitelanguage', 0);
        if ($sitelanguage > 0) {
            $folder = Locale::folderForId($sitelanguage, $langFolder);
            if ($folder !== '') {
                Locale::setFolderCookie($folder);
                $query = $request->query();
                unset($query['sitelanguage']);

                return Redirect::to('/confirm_resend'.(empty($query) ? '' : '?'.http_build_query($query)));
            }
        }

        $secret = (string) $request->query('secret', '');
        $captchaEnabled = $this->authService->isCaptchaEnabled();
        $captchaMarkup = '';

        if ($captchaEnabled) {
            $captchaMarkup = Captcha::renderHtml('yes', $secret, 'grid');
        }

        return view('auth.confirm_resend', [
            'langFolder' => $langFolder,
            'languages' => Locale::languageList('site_lang', true),
            'captchaEnabled' => $captchaEnabled,
            'captchaMarkup' => SafeHtml::fromTrustedHtml($captchaMarkup),
            'secret' => $secret,
            'remaining' => $this->authService->remainingAttempts(Network::clientIp()),
            'maxAttempts' => $this->authService->maxLoginAttempts(),
            'error' => $request->session()->get('error'),
            'siteName' => Setting::getSiteName(),
        ]);
    }

    public function resendConfirmation(ConfirmResendRequest $request): RedirectResponse
    {
        if (Auth::guard('nexus-web')->check()) {
            return Redirect::to('/web/index');
        }

        $langFolder = $this->resolveLangFolder($request);

        try {
            $redirect = $this->registrationService->resendConfirmation(
                $request->validated(),
                Network::clientIp(),
                $langFolder,
            );
        } catch (AuthenticationException $exception) {
            return $this->backWithError($request, $exception->getMessage());
        }

        return Redirect::to($redirect);
    }

    private function resolveLangFolder(Request $request): string
    {
        $folder = $request->cookie('c_lang_folder');
        if (! is_string($folder)) {
            $folder = '';
        }

        return Locale::folderFromCookie($folder);
    }

    private function backWithError(Request $request, string $message): RedirectResponse
    {
        return Redirect::back()
            ->withInput($request->except('wantpassword', 'passagain'))
            ->with('error', $message);
    }
}
