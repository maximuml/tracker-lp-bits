<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Http\Requests\Auth\RecoverRequest;
use App\Models\Setting;
use App\Services\PasswordRecoveryService;
use App\Services\WebAuthService;
use App\Support\Captcha;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\Support\Network;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\ViewErrorBag;

class RecoveryController extends Controller
{
    public function __construct(
        private PasswordRecoveryService $recoveryService,
        private WebAuthService $authService,
    ) {}

    public function recover(RecoverRequest $request): Response|RedirectResponse
    {
        if ($early = $this->recoverPreamble($request)) {
            return $early;
        }

        $langFolder = $this->resolveLangFolder($request);

        $id = (int) $request->query('id', 0);
        $secret = (string) $request->query('secret', '');
        $hasResetLink = $request->query->has('id') || $request->query->has('secret');
        $resetToken = $id > 0 && $secret !== ''
            ? $this->recoveryService->validateResetToken($id, $secret)
            : null;

        return $this->renderRecover(
            $request,
            $langFolder,
            $id > 0 ? $id : null,
            $resetToken !== null ? $secret : null,
            $hasResetLink && $resetToken === null
                ? (string) __('recover.std_invalid_reset_link')
                : null,
            $hasResetLink,
        );
    }

    public function recoverPost(RecoverRequest $request): Response|RedirectResponse
    {
        if ($early = $this->recoverPreamble($request)) {
            return $early;
        }

        try {
            $this->recoveryService->requestReset($request->validated(), Network::clientIp());
        } catch (AuthenticationException $exception) {
            return $this->backWithError($request, $exception->getMessage());
        }

        return Redirect::to('/recover?status=requested');
    }

    private function recoverPreamble(RecoverRequest $request): ?RedirectResponse
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

                return Redirect::to('/recover'.(empty($query) ? '' : '?'.http_build_query($query)));
            }
        }

        return null;
    }

    public function resetPassword(PasswordResetRequest $request): RedirectResponse
    {
        if (Auth::guard('nexus-web')->check()) {
            return Redirect::to('/web/index');
        }

        $validated = $request->validated();
        $id = (int) $validated['id'];
        $secret = (string) $validated['secret'];

        try {
            $this->recoveryService->resetPassword(
                $id,
                $secret,
                (string) $validated['password'],
                (string) $validated['password_confirmation'],
            );
        } catch (AuthenticationException $exception) {
            return Redirect::to($this->recoverUrl($id, $secret))
                ->with('error', $exception->getMessage());
        }

        return Redirect::to('/login?status=reset');
    }

    private function renderRecover(
        Request $request,
        string $langFolder,
        ?int $resetUserId,
        ?string $resetToken,
        ?string $resetError,
        bool $isResetLink,
    ): Response {
        $captchaEnabled = $this->authService->isCaptchaEnabled();
        $captchaMarkup = '';

        if ($captchaEnabled && $resetToken === null) {
            $captchaMarkup = Captcha::renderHtml('yes', (string) $request->query('secret', ''), 'grid');
        }

        $response = response()->view('auth.recover', [
            'langFolder' => $langFolder,
            'languages' => Locale::languageList('site_lang', true),
            'captchaEnabled' => $captchaEnabled,
            'captchaMarkup' => SafeHtml::fromTrustedHtml($captchaMarkup),
            'resetUserId' => $resetUserId,
            'resetToken' => $resetToken,
            'resetError' => $resetError,
            'status' => $request->query('status', ''),
            'remaining' => $this->authService->remainingAttempts(Network::clientIp()),
            'maxAttempts' => $this->authService->maxLoginAttempts(),
            'siteName' => Setting::getSiteName(),
            'error' => $request->hasSession() ? $request->session()->get('error') : null,
            'errors' => $request->hasSession()
                ? $request->session()->get('errors', new ViewErrorBag)
                : new ViewErrorBag,
        ]);

        if ($isResetLink) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    private function resolveLangFolder(Request $request): string
    {
        $folder = $request->cookie('c_lang_folder');
        if (! is_string($folder)) {
            $folder = '';
        }

        return Locale::folderFromCookie($folder);
    }

    private function recoverUrl(int $id, string $secret): string
    {
        return '/recover?'.http_build_query([
            'id' => $id,
            'secret' => $secret,
        ]);
    }

    private function backWithError(Request $request, string $message): RedirectResponse
    {
        return Redirect::back()
            ->withInput($request->except('wantpassword', 'passagain', 'password', 'password_confirmation'))
            ->with('error', $message);
    }
}
