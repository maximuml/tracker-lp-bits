<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Captcha\Drivers\ImageCaptchaDriver;
use App\Services\WebAuthService;
use App\Support\AssetAppender;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\Html\SafeHtml;
use App\Support\Http\SafeReturnUrl;
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

    public function __construct(
        WebAuthService $authService,
    ) {
        $this->authService = $authService;
    }

    public function showLogin(Request $request): View|RedirectResponse
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

                return Redirect::to('/login'.(empty($query) ? '' : '?'.http_build_query($query)));
            }
        }

        $secret = (string) $request->query('secret', '');
        $returnto = (string) $request->query('returnto', '');
        $nowarn = $request->has('nowarn');

        $captchaEnabled = $this->authService->isCaptchaEnabled();
        $captchaMarkup = '';
        if ($captchaEnabled) {
            $driver = Captcha::manager()->driver();
            $imageLabelKey = $driver instanceof ImageCaptchaDriver
                ? 'row_security_image'
                : 'row_security_challenge';

            $captchaMarkup = $driver->render([
                'labels' => [
                    'image' => __('legacy/functions.'.$imageLabelKey),
                    'code' => __('legacy/functions.row_security_code'),
                ],
                'secret' => $secret,
                'layout' => 'grid',
            ]);
        }

        $view = view('auth.login', [
            'languages' => Locale::languageList('site_lang', true),
            'langFolder' => $langFolder,
            'secret' => $secret,
            'returnto' => $returnto,
            'captchaEnabled' => $captchaEnabled,
            'captchaMarkup' => SafeHtml::fromTrustedHtml($captchaMarkup),
            'remaining' => $this->authService->remainingAttempts(Network::clientIp()),
            'maxAttempts' => $this->authService->maxLoginAttempts(),
            'nowarn' => $nowarn,
            'error' => $request->session()->get('error'),
            'isComplainEnabled' => Setting::getIsComplainEnabled(),
            'siteName' => Setting::getSiteName(),
            'showWarn' => $returnto !== '' && ! $nowarn,
            'isSmtpEnabled' => SiteConfig::current()->smtp->type() !== 'none',
        ]);

        AssetAppender::js('js/passkey.js', 'footer', true);

        return $view;
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
        if (is_string($returnto) && $returnto !== '') {
            return Redirect::to(SafeReturnUrl::filter($returnto, '/web/index'));
        }

        return Redirect::to('/web/index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout();

        Auth::guard('web')->logout();

        return Redirect::to('/login');
    }

    public function logoutAllDevices(Request $request): RedirectResponse
    {
        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return Redirect::to('/login');
        }

        $this->authService->logoutAllDevices($user);
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

    private function backWithError(Request $request, string $message): RedirectResponse
    {
        return Redirect::back()
            ->withInput($request->except('password'))
            ->with('error', $message);
    }
}
