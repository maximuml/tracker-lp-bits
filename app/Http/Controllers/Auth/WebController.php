<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\WebAuthService;
use App\Support\Captcha;
use App\Support\Locale;
use App\Support\Network;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class WebController extends Controller
{
    private WebAuthService $authService;

    public function __construct(WebAuthService $authService)
    {
        $this->authService = $authService;
    }

    public function showLogin(Request $request): View|RedirectResponse
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
                return Redirect::to('/login');
            }
        }

        $secret = (string) $request->query('secret', '');
        $returnto = (string) $request->query('returnto', '');
        $nowarn = $request->has('nowarn');

        $captchaEnabled = $this->authService->isCaptchaEnabled();
        $captchaMarkup = '';
        if ($captchaEnabled) {
            $captchaMarkup = Captcha::manager()->driver()->render([
                'labels' => $this->langFunctions($langFolder),
                'secret' => $secret,
            ]);
        }

        return view('auth.login', [
            'lang' => $this->langLogin($langFolder),
            'langFunctions' => $this->langFunctions($langFolder),
            'languages' => Locale::languageList('site_lang', true),
            'langFolder' => $langFolder,
            'secret' => $secret,
            'returnto' => $returnto,
            'captchaEnabled' => $captchaEnabled,
            'captchaMarkup' => $captchaMarkup,
            'remaining' => $this->authService->remainingAttempts(Network::clientIp()),
            'maxAttempts' => $this->authService->maxLoginAttempts(),
            'nowarn' => $nowarn,
            'error' => $request->session()->get('error'),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (Auth::guard('nexus-web')->check()) {
            return Redirect::intended('index.php');
        }

        try {
            $this->authService->authenticate($request->validated(), Network::clientIp());
        } catch (AuthenticationException $exception) {
            return Redirect::back()->withInput()->with('error', $exception->getMessage());
        }

        $returnto = $request->input('returnto', '');
        if (is_string($returnto) && $returnto !== '' && $this->isLocalUrl($returnto)) {
            return Redirect::to($returnto);
        }

        return Redirect::to('index.php');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout();

        Auth::guard('web')->logout();

        return Redirect::to('/login');
    }

    private function resolveLangFolder(Request $request): string
    {
        $folder = $request->cookie('c_lang_folder');
        if (! is_string($folder)) {
            $folder = '';
        }

        return Locale::folderFromCookie($folder);
    }

    /**
     * @return array<string, string>
     */
    private function langLogin(string $langFolder): array
    {
        $path = Locale::filePath($langFolder, 'login.php');
        if (! file_exists($path)) {
            return [];
        }

        include $path;

        return $lang_login ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function langFunctions(string $langFolder): array
    {
        $path = Locale::filePath($langFolder, 'functions.php');
        if (! file_exists($path)) {
            return [];
        }

        include $path;

        return $lang_functions ?? [];
    }

    private function isLocalUrl(string $url): bool
    {
        return ! str_starts_with($url, 'http://')
            && ! str_starts_with($url, 'https://')
            && ! str_starts_with($url, '//');
    }
}
