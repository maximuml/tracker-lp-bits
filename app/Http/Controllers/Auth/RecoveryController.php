<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RecoverRequest;
use App\Services\PasswordRecoveryService;
use App\Services\WebAuthService;
use App\Support\Captcha;
use App\Support\Locale;
use App\Support\Network;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class RecoveryController extends Controller
{
    public function __construct(
        private PasswordRecoveryService $recoveryService,
        private WebAuthService $authService,
    ) {
    }

    public function recover(Request $request): View|RedirectResponse
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

                return Redirect::to('/recover' . (empty($query) ? '' : '?' . http_build_query($query)));
            }
        }

        $langRecover = $this->langRecover($langFolder);
        $langFunctions = $this->langFunctions($langFolder);

        if ($request->isMethod('post')) {
            $validator = Validator::make($request->all(), (new \App\Http\Requests\Auth\RecoverRequest())->rules());

            if ($validator->fails()) {
                return $this->backWithError($request, $validator->errors()->first());
            }

            try {
                $this->recoveryService->requestReset($validator->validated(), Network::clientIp(), $langRecover, $langFunctions);
            } catch (AuthenticationException $exception) {
                return $this->backWithError($request, $exception->getMessage());
            }

            return Redirect::to('/recover?status=requested');
        }

        $id = (int) $request->query('id', 0);
        $secret = (string) $request->query('secret', '');

        if ($id > 0 && $secret !== '') {
            try {
                $this->recoveryService->resetPassword($id, $secret, $langRecover);
            } catch (AuthenticationException $exception) {
                return $this->backWithError($request, $exception->getMessage());
            }

            return Redirect::to('/login?status=reset');
        }

        $secret = (string) $request->query('secret', '');
        $captchaEnabled = $this->authService->isCaptchaEnabled();
        $captchaMarkup = '';

        if ($captchaEnabled) {
            ob_start();
            Captcha::render('yes', $langFunctions, $secret);
            $captchaMarkup = (string) ob_get_clean();
        }

        return view('auth.recover', [
            'lang' => $langRecover,
            'langFunctions' => $langFunctions,
            'langFolder' => $langFolder,
            'languages' => Locale::languageList('site_lang', true),
            'captchaEnabled' => $captchaEnabled,
            'captchaMarkup' => $captchaMarkup,
            'secret' => $secret,
            'status' => $request->query('status', ''),
            'remaining' => $this->authService->remainingAttempts(Network::clientIp()),
            'maxAttempts' => $this->authService->maxLoginAttempts(),
            'error' => $request->session()->get('error'),
        ]);
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
    private function langRecover(string $langFolder): array
    {
        return $this->loadLangFile($langFolder, 'recover.php', 'lang_recover');
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
            ->withInput($request->except('wantpassword', 'passagain'))
            ->with('error', $message);
    }
}
