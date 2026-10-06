<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NexusException;
use App\Http\Requests\Auth\ChallengeRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PasskeyLoginRequest;
use App\Http\Requests\Auth\PasskeyLoginV2Request;
use App\Models\User;
use App\Repositories\AuthenticateRepository;
use App\Services\PasskeyLoginService;
use App\Support\AuthCookie;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use App\Support\Network;
use App\Support\RedisGuard;
use App\Support\Token;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AuthenticateController extends Controller
{
    private AuthenticateRepository $repository;

    private UserRepositoryInterface $userRepository;

    public function __construct(
        AuthenticateRepository $repository,
        UserRepositoryInterface $userRepository,
        private readonly Container $container,
    ) {
        $this->repository = $repository;
        $this->userRepository = $userRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function login(LoginRequest $request): array
    {
        try {
            $result = $this->repository->login(
                $request->username,
                $request->password,
                (string) $request->input('two_step_code', ''),
                Network::clientIp(),
            );
        } catch (\InvalidArgumentException|AuthenticationException|NexusException $e) {
            abort(401, $e->getMessage());
        }
        $includes = explode(',', $request->get('include', ''));
        if (in_array('site_info', $includes)) {
            $result['site_info'] = [
                'site_name' => SiteConfig::current()->basic->siteName(),
            ];
        }

        return $this->success($result);
    }

    /**
     * Logout revokes the *current* access token only — ending every
     * device session is a separate action (logoutAll).
     *
     * @return array<string, mixed>
     */
    public function logout(Request $request): array
    {
        $token = $request->user()?->currentAccessToken();
        $token?->delete();

        return $this->success(['revoked' => 'current']);
    }

    /**
     * @return array<string, mixed>
     */
    public function logoutAll(Request $request): array
    {
        $result = $this->repository->logout((int) Auth::id());

        return $this->success($result);
    }

    /**
     * Authenticate via BitTorrent passkey with HMAC replay protection.
     *
     * Required parameters:
     * - passkey: 32-char hex string (user's BitTorrent passkey)
     * - timestamp: unix timestamp (seconds)
     * - signature: hmac_sha256(passkey + timestamp, login_secret)
     *
     * The timestamp must be within ±5 minutes of server time.
     * Each signature can only be used once — an atomic cache marker
     * prevents replay for the signature's remaining validity window.
     */
    public function passkeyLogin(PasskeyLoginRequest $request): RedirectResponse
    {
        $passkey = $request->input('passkey');
        $timestamp = (int) $request->input('timestamp');
        $signature = (string) $request->input('signature');

        $loginSecret = SiteConfig::current()->security->loginSecret();
        $deadline = SiteConfig::current()->security->loginSecretDeadline();

        // Validate HMAC signature to prevent replay attacks
        $expected = hash_hmac('sha256', $passkey.$timestamp, $loginSecret);
        if (! hash_equals($expected, $signature)) {
            Logger::writeWithContext((string) 'passkeyLogin: invalid HMAC signature', (string) 'warning', (bool) false);

            return redirect('/web/index');
        }

        // Validate timestamp is within ±5 minutes
        $now = time();
        if (abs($now - $timestamp) > 300) {
            Logger::writeWithContext((string) sprintf('passkeyLogin: timestamp out of window (server=%d, client=%d)', $now, $timestamp), (string) 'warning', (bool) false);

            return redirect('/web/index');
        }

        // Replay protection: atomic "set if not exists". The marker must
        // outlive the signature's validity — TTL is the remaining
        // signature lifetime, not a fixed 300 s from arrival. A failing
        // store rejects the login (fail closed) rather than silently
        // disabling replay protection.
        $replayKey = 'passkey_login_used:'.hash('sha256', $signature);
        try {
            $stored = Cache::add($replayKey, '1', now()->addSeconds(PasskeyLoginService::nonceTtlSeconds($timestamp, $now)));
        } catch (\Throwable $e) {
            Logger::writeWithContext((string) sprintf('passkeyLogin: replay marker store unavailable (%s) — rejecting login', $e->getMessage()), (string) 'error', (bool) false);

            return redirect('/web/index');
        }
        if ($stored === false) {
            Logger::writeWithContext((string) 'passkeyLogin: replay detected — signature already used', (string) 'warning', (bool) false);

            return redirect('/web/index');
        }

        if ($deadline && $deadline > now()->toDateTimeString()) {
            $user = $this->userRepository->findByPasskey($passkey, ['id', 'username', 'passhash', 'secret', 'auth_key', 'status', 'enabled']);
            if ($user && $this->userCanLogin($user, 'passkeyLogin')) {
                $ip = Network::clientIp();
                AuthCookie::setLoginCookie((int) $user->id, null, (int) 0);
                $user->last_login = now();
                $user->save();
                $this->userRepository->saveLoginLog($user->id, $ip, 'Passkey', false);
            }
        }

        return redirect('/web/index');
    }

    /**
     * Passkey login v2 — HMAC-SHA256 with canonical payload, nonce
     * replay protection, and key rotation by key ID.
     *
     * Required parameters:
     * - passkey: 32-char hex string (user's BitTorrent passkey)
     * - timestamp: unix timestamp (seconds)
     * - nonce: 32-char hex string (unique per request)
     * - signature: 64-char hex HMAC-SHA256
     * - key_id: signing key identifier
     * - action: action scope (default: "login")
     *
     * The timestamp must be within ±5 minutes of server time.
     * Each nonce can only be used once — Redis SET NX EX prevents replay.
     */
    public function passkeyLoginV2(PasskeyLoginV2Request $request, PasskeyLoginService $service): RedirectResponse
    {
        $passkey = (string) $request->input('passkey');
        $timestamp = (int) $request->input('timestamp');
        $nonce = (string) $request->input('nonce');
        $signature = (string) $request->input('signature');
        $keyId = (string) $request->input('key_id');
        $action = (string) $request->input('action', PasskeyLoginService::ACTION_LOGIN);

        if (! $service->verify($passkey, $timestamp, $nonce, $signature, $keyId, $action)) {
            return redirect('/web/index');
        }

        $deadline = SiteConfig::current()->security->loginSecretDeadline();
        if ($deadline && $deadline > now()->toDateTimeString()) {
            $user = $this->userRepository->findByPasskey($passkey, ['id', 'username', 'passhash', 'secret', 'auth_key', 'status', 'enabled']);
            if ($user && $this->userCanLogin($user, 'passkeyLoginV2')) {
                $ip = Network::clientIp();
                AuthCookie::setLoginCookie((int) $user->id, null, (int) 0);
                $user->last_login = now();
                $user->save();
                $this->userRepository->saveLoginLog($user->id, $ip, 'Passkey', false);
            }
        }

        return redirect('/web/index');
    }

    /**
     * Dispatcher for the legacy passkey login secret URI.
     *
     * `login_secret` is admin-configured and can change without a deploy,
     * so it cannot be a static route under `route:cache`. Reached through
     * the last-registered POST catch-all: when a POST hits the configured
     * secret (and passkey login is enabled and not past its deadline) the
     * request is dispatched to the same controller action; every other
     * unmatched path gets the standard 404.
     */
    public function legacyPasskeyFallback(Request $request): RedirectResponse
    {
        $security = SiteConfig::current()->security;
        $secret = $security->loginSecret();
        $deadline = $security->loginSecretDeadline();

        if (
            ! $request->isMethod('POST')
            || $secret === ''
            || $security->loginType() !== 'passkey'
            || $deadline === null
            || $deadline <= now()->toDateTimeString()
            || $request->path() !== ltrim($secret, '/')
        ) {
            abort(404);
        }

        return $this->passkeyLogin($this->container->make(PasskeyLoginRequest::class));
    }

    /**
     * Disabled or unconfirmed accounts must not authenticate via passkey
     * either — same contract as the WebAuthn passkey flow and the web
     * guard. checkIsNormal() throws NexusException; a rejection is logged
     * and answered like any other failed attempt (silent redirect).
     */
    private function userCanLogin(User $user, string $context): bool
    {
        try {
            return $user->checkIsNormal(['status', 'enabled']);
        } catch (\Throwable $e) {
            Logger::writeWithContext(
                (string) sprintf('%s: user %d rejected (%s)', $context, (int) $user->id, $e->getMessage()),
                (string) 'warning',
                (bool) false,
            );

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function challenge(ChallengeRequest $request): array
    {
        try {
            $username = $request->username;
            $challenge = Token::randomHex((int) 20);
            RedisGuard::attempt(static fn () => Cache::put(Token::challengeKey($username), $challenge, 300), false);
            $user = $this->userRepository->findByUsername($username, ['secret', 'passhash_algo']);

            return $this->success([
                'challenge' => $challenge,
                'secret' => $user->secret ?? Token::randomHex((int) 20),
                'passhash_algo' => $user->passhash_algo ?? 'sha256',
            ]);
        } catch (\Exception $exception) {
            $msg = $exception->getMessage();
            Logger::writeWithContext((string) sprintf('challenge fail: %s', $msg), (string) 'info', (bool) false);

            return $this->fail([], $msg);
        }
    }
}
