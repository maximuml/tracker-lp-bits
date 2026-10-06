<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Requests\PreviewRequest;
use App\Models\Setting;
use App\Repositories\UsercpSecurityCommand;
use App\Services\SecureTokenService;
use App\Support\Api;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Captcha;
use App\Support\CurrentUser;
use App\Support\LegacyAjaxRedirects;
use App\Support\LegacyAuth;
use App\Support\LegacyHeaderBag;
use App\Support\Logger;
use App\Support\RedisGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;

class UtilityController extends LegacyController
{
    public function __construct(
        private readonly UsercpSecurityCommand $usercpSecurityCommand,
        private readonly UserRepositoryInterface $userRepository,
        private readonly CurrentUser $currentUser,
        private readonly ?LegacyRedisCache $legacyRedisCache,
        private readonly LegacyHeaderBag $legacyHeaderBag,
        private readonly SecureTokenService $secureTokenService,
    ) {}

    public function ajax(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->legacyRedisCache === null) {
            $qs = $request->getQueryString();

            return redirect('/ajax'.($qs ? '?'.$qs : ''));
        }

        $action = (string) $request->input('action', '');

        // Count shim hits per action so the /ajax route can be dropped
        // once this family goes quiet (exposed via /metrics; unmapped
        // actions collapse to __invalid to bound label cardinality).
        $label = LegacyAjaxRedirects::uriFor($action) !== null ? $action : '__invalid';
        RedisGuard::attempt(static function () use ($label) {
            $redis = Redis::connection();
            $redis->incr("metrics:legacy_ajax:{$label}");
            $redis->sadd('metrics:legacy_ajax_actions', $label);
        });

        // The two login-page passkey assertions ran pre-auth in the old
        // dispatcher — their REST endpoints are guest-facing too.
        $guestActions = ['getPasskeyGetArgs', 'processPasskeyGet'];
        if (! in_array($action, $guestActions, true)) {
            LegacyAuth::requireLoginFromContext();
        }

        // Every action migrated to its own REST endpoint — 308 redirects
        // replay method + body, so legacy {action, params} POSTs land
        // there byte-identically and the target FormRequest flattens
        // the envelope.
        $redirectUri = LegacyAjaxRedirects::uriFor($action);
        if ($redirectUri !== null) {
            return redirect()->to($redirectUri, 308);
        }

        $currentUser = $this->currentUser->get() ?? [];
        Logger::writeWithContext((string) ('hacking attempt made by '.($currentUser['username'] ?? 'guest').',uid '.($currentUser['id'] ?? 0)), (string) 'error', (bool) false);

        return response()->json(Api::call(1, "Invalid action: {$action}", $request->only(['action', 'params'])));
    }

    public function image(Request $request): Response|RedirectResponse
    {
        $action = (string) $request->input('action', '');
        $imagehash = (string) $request->input('imagehash', '');

        if ($action !== 'regimage') {
            return response('Invalid captcha action', 404);
        }

        $driver = Captcha::manager()->driver('image');

        if (! method_exists($driver, 'imageBytes')) {
            return response('Captcha driver does not support image rendering', 404);
        }

        $content = $driver->imageBytes($imagehash);

        // T-11: Read from the per-request LegacyHeaderBag instead of SAPI
        // globals that leak state across Octane worker requests.
        $headerBag = $this->legacyHeaderBag;
        $status = $headerBag->getStatusCode();
        $headers = $headerBag->toResponseHeaders();
        $headerBag->flush();

        $responseStatus = $status !== null && $status >= 100 ? $status : 200;

        return response($content, $responseStatus, $headers);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        return $this->renderPreview($request);
    }

    public function previewSubmit(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/preview'.$suffix, 308);
    }

    public function previewRender(PreviewRequest $request): View|RedirectResponse
    {
        return $this->renderPreview($request);
    }

    private function renderPreview(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'preview', true, [
            'body' => (string) $request->post('body', ''),
        ]);
    }

    public function moresmilies(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'moresmilies', true, [
            'form' => (string) $request->query('form', ''),
            'text' => (string) $request->query('text', ''),
        ]);
    }

    public function smilies(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'smilies', true);
    }

    public function confirmemail(Request $request): Response|RedirectResponse
    {
        $routePath = $request->route('path') ?? '';
        $pathInfo = $routePath !== '' ? '/'.ltrim((string) $routePath, '/') : '';
        if (! preg_match(':^/(\d{1,10})/([\w]{32,64})/(.+)$:', $pathInfo, $matches)) {
            abort(404);
        }

        $id = (int) $matches[1];
        $token = $matches[2];
        $email = urldecode($matches[3]);

        if ($id <= 0) {
            abort(404);
        }

        $validator = validator(['email' => $email], [
            'email' => 'required|email|max:255',
        ]);
        if ($validator->fails()) {
            abort(404);
        }

        $user = $this->userRepository->findById($id, ['editsecret']);
        if (! $user) {
            abort(404);
        }

        if (! $this->secureTokenService->verifyEmailChangeToken((string) $user->editsecret, $email, $token)) {
            abort(404);
        }

        $affected = $this->usercpSecurityCommand->applyEmailChange($id, (string) $user->editsecret, $email);
        if (! $affected) {
            abort(404);
        }

        return redirect('/usercp?action=security&type=saved');
    }

    public function ok(Request $request): View|RedirectResponse
    {
        $type = (string) $request->input('type', '');
        $email = '';
        if ($type === 'signup') {
            $email = (string) $request->input('email', '');
        }

        $title = match ($type) {
            'adminactivate', 'inviter', 'signup' => __('legacy/ok.head_user_signup'),
            'sysop' => __('legacy/ok.head_sysop_activation'),
            'confirmed' => __('legacy/ok.head_already_confirmed'),
            'confirm' => __('legacy/ok.head_signup_confirmation'),
            default => '',
        };

        return $this->legacyPage($request, 'ok', false, [
            'type' => $type,
            'email' => $email,
            'title' => $title,
            'siteName' => Setting::getSiteName(),
            'CURUSER' => $this->currentUser->get(),
        ]);
    }
}
