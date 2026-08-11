<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmResendRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Models\Invite;
use App\Services\RegistrationService;
use App\Services\WebAuthService;
use App\Support\Captcha;
use App\Support\Locale;
use App\Support\Network;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function __construct(
        private RegistrationService $registrationService,
        private WebAuthService $authService,
    ) {
    }

    public function showSignup(Request $request): View|RedirectResponse
    {
        if (Auth::guard('nexus-web')->check()) {
            return Redirect::to('index.php');
        }

        $langFolder = $this->resolveLangFolder($request);

        $sitelanguage = (int) $request->query('sitelanguage', 0);
        if ($sitelanguage > 0) {
            $folder = Locale::folderForId($sitelanguage, $langFolder);
            if ($folder !== '') {
                Locale::setFolderCookie($folder);
                $query = $request->query();
                unset($query['sitelanguage']);

                return Redirect::to('/signup' . (empty($query) ? '' : '?' . http_build_query($query)));
            }
        }

        $type = (string) $request->query('type', '');
        $isInvite = $type === 'invite';
        $code = trim((string) ($request->query('invitenumber', $request->query('hash', ''))));
        $secret = (string) $request->query('secret', '');

        $invite = null;
        if ($isInvite && $code !== '') {
            $invite = Invite::query()
                ->where('hash', $code)
                ->where('valid', Invite::VALID_YES)
                ->first();
        }

        $langSignup = $this->langSignup($langFolder);
        $langFunctions = $this->langFunctions($langFolder);
        $captchaEnabled = $this->authService->isCaptchaEnabled();
        $captchaMarkup = '';

        if ($captchaEnabled) {
            ob_start();
            Captcha::render('yes', $langFunctions, $secret);
            $captchaMarkup = (string) ob_get_clean();
        }

        $countries = DB::table('countries')->orderBy('name')->get(['id', 'name']);

        return view('auth.signup', [
            'lang' => $langSignup,
            'langFunctions' => $langFunctions,
            'langFolder' => $langFolder,
            'languages' => Locale::languageList('site_lang', true),
            'captchaEnabled' => $captchaEnabled,
            'captchaMarkup' => $captchaMarkup,
            'secret' => $secret,
            'type' => $type,
            'isInvite' => $isInvite,
            'invite' => $invite,
            'code' => $code,
            'isPreRegisterEmailAndUsername' => \App\Support\Config\SiteConfig::current()->system->isInvitePreEmailAndUsername(),
            'countries' => $countries,
            'remaining' => $this->authService->remainingAttempts(Network::clientIp()),
            'maxAttempts' => $this->authService->maxLoginAttempts(),
            'error' => $request->session()->get('error'),
        ]);
    }

    public function signup(SignupRequest $request): RedirectResponse
    {
        if (Auth::guard('nexus-web')->check()) {
            return Redirect::to('index.php');
        }

        $langFolder = $this->resolveLangFolder($request);
        $langSignup = $this->langSignup($langFolder);
        $langTakesignup = $this->langTakesignup($langFolder);
        $langFunctions = $this->langFunctions($langFolder);

        try {
            $result = $this->registrationService->signup(
                $request->validated(),
                Network::clientIp(),
                $langFolder,
                $langSignup,
                $langTakesignup,
                $langFunctions,
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
            return Redirect::to('ok.php?type=confirmed');
        }

        if ($user->status !== 'pending' && $user->status !== 'confirmed') {
            return Redirect::to('ok.php?type=confirmed');
        }

        return Redirect::to('ok.php?type=confirm');
    }

    public function showConfirmResend(Request $request): View|RedirectResponse
    {
        if (Auth::guard('nexus-web')->check()) {
            return Redirect::to('index.php');
        }

        $langFolder = $this->resolveLangFolder($request);

        $sitelanguage = (int) $request->query('sitelanguage', 0);
        if ($sitelanguage > 0) {
            $folder = Locale::folderForId($sitelanguage, $langFolder);
            if ($folder !== '') {
                Locale::setFolderCookie($folder);
                $query = $request->query();
                unset($query['sitelanguage']);

                return Redirect::to('/confirm_resend' . (empty($query) ? '' : '?' . http_build_query($query)));
            }
        }

        $langConfirmResend = $this->langConfirmResend($langFolder);
        $langFunctions = $this->langFunctions($langFolder);
        $secret = (string) $request->query('secret', '');
        $captchaEnabled = $this->authService->isCaptchaEnabled();
        $captchaMarkup = '';

        if ($captchaEnabled) {
            ob_start();
            Captcha::render('yes', $langFunctions, $secret);
            $captchaMarkup = (string) ob_get_clean();
        }

        return view('auth.confirm_resend', [
            'lang' => $langConfirmResend,
            'langFunctions' => $langFunctions,
            'langFolder' => $langFolder,
            'languages' => Locale::languageList('site_lang', true),
            'captchaEnabled' => $captchaEnabled,
            'captchaMarkup' => $captchaMarkup,
            'secret' => $secret,
            'remaining' => $this->authService->remainingAttempts(Network::clientIp()),
            'maxAttempts' => $this->authService->maxLoginAttempts(),
            'error' => $request->session()->get('error'),
        ]);
    }

    public function resendConfirmation(ConfirmResendRequest $request): RedirectResponse
    {
        if (Auth::guard('nexus-web')->check()) {
            return Redirect::to('index.php');
        }

        $langFolder = $this->resolveLangFolder($request);
        $langConfirmResend = $this->langConfirmResend($langFolder);
        $langFunctions = $this->langFunctions($langFolder);

        try {
            $redirect = $this->registrationService->resendConfirmation(
                $request->validated(),
                Network::clientIp(),
                $langFolder,
                $langConfirmResend,
                $langFunctions,
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

    /** @return array<string, string> */
    private function langSignup(string $langFolder): array
    {
        return $this->loadLangFile($langFolder, 'signup.php', 'lang_signup');
    }

    /** @return array<string, string> */
    private function langTakesignup(string $langFolder): array
    {
        return $this->loadLangFile($langFolder, 'takesignup.php', 'lang_takesignup');
    }

    /** @return array<string, string> */
    private function langConfirmResend(string $langFolder): array
    {
        return $this->loadLangFile($langFolder, 'confirm_resend.php', 'lang_confirm_resend');
    }

    /** @return array<string, string> */
    private function langFunctions(string $langFolder): array
    {
        return $this->loadLangFile($langFolder, 'functions.php', 'lang_functions');
    }

    /** @return array<string, string> */
    private function loadLangFile(string $langFolder, string $scriptName, string $variableName): array
    {
        $path = base_path(Locale::filePath($langFolder, $scriptName));
        if (! file_exists($path)) {
            return [];
        }

        include $path;

        return ${$variableName} ?? [];
    }

    private function backWithError(Request $request, string $message): RedirectResponse
    {
        return Redirect::back()
            ->withInput($request->except('wantpassword', 'passagain', 'wantpassword_hashed'))
            ->with('error', $message);
    }
}
