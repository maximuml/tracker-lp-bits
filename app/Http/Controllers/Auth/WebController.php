<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Setting;
use App\Repositories\UserPasskeyRepository;
use App\Services\Captcha\Drivers\ImageCaptchaDriver;
use App\Services\WebAuthService;
use App\Support\AssetAppender;
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

                $query = $request->query();
                unset($query['sitelanguage']);

                return Redirect::to('/login'.(empty($query) ? '' : '?'.http_build_query($query)));
            }
        }

        $secret = (string) $request->query('secret', '');
        $returnto = (string) $request->query('returnto', '');
        $nowarn = $request->has('nowarn');

        $langFunctions = $this->langFunctions($langFolder);

        $captchaEnabled = $this->authService->isCaptchaEnabled();
        $captchaMarkup = '';
        if ($captchaEnabled) {
            $driver = Captcha::manager()->driver();
            $imageLabelKey = $driver instanceof ImageCaptchaDriver
                ? 'row_security_image'
                : 'row_security_challenge';

            $captchaMarkup = $driver->render([
                'labels' => [
                    'image' => $langFunctions[$imageLabelKey] ?? $langFunctions['row_security_image'] ?? 'Security Image',
                    'code' => $langFunctions['row_security_code'] ?? 'Security Code',
                ],
                'secret' => $secret,
            ]);
        }

        return view('auth.login', [
            'lang' => $this->langLogin($langFolder),
            'langFunctions' => $langFunctions,
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
            'isComplainEnabled' => Setting::getIsComplainEnabled(),
            'passkeyLoginHtml' => $this->renderPasskeyLogin(),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (Auth::guard('nexus-web')->check()) {
            return Redirect::intended('index.php');
        }

        $ip = Network::clientIp();

        try {
            $this->authService->assertNotBanned($ip);
        } catch (AuthenticationException $exception) {
            return $this->backWithError($request, $exception->getMessage());
        }

        try {
            $this->authService->authenticate($request->validated(), $ip);
        } catch (AuthenticationException $exception) {
            return $this->backWithError($request, $exception->getMessage());
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
        $path = base_path(Locale::filePath($langFolder, 'login.php'));
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
        $path = base_path(Locale::filePath($langFolder, 'functions.php'));
        if (! file_exists($path)) {
            return [];
        }

        include $path;

        return $lang_functions ?? [];
    }

    private function renderPasskeyLogin(): string
    {
        ob_start();
        app(UserPasskeyRepository::class)->renderLogin();
        AssetAppender::js('js/passkey.js', 'footer', true);

        return (string) ob_get_clean();
    }

    private function backWithError(Request $request, string $message): RedirectResponse
    {
        return Redirect::back()
            ->withInput($request->except('password'))
            ->with('error', $message);
    }

    private function isLocalUrl(string $url): bool
    {
        return ! str_starts_with($url, 'http://')
            && ! str_starts_with($url, 'https://')
            && ! str_starts_with($url, '//');
    }
}
