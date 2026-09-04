<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ChallengeRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PasskeyLoginRequest;
use App\Http\Requests\Auth\PasskeyLoginV2Request;
use App\Models\User;
use App\Repositories\AuthenticateRepository;
use App\Repositories\UserRepository;
use App\Services\PasskeyLoginService;
use App\Support\AuthCookie;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use App\Support\Network;
use App\Support\Token;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AuthenticateController extends Controller
{
    private AuthenticateRepository $repository;

    private UserRepository $userRepository;

    public function __construct(
        AuthenticateRepository $repository,
        UserRepository $userRepository,
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
            $result = $this->repository->login($request->username, $request->password, (string) $request->input('two_step_code', ''));
        } catch (\InvalidArgumentException $e) {
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
     * @return array<string, mixed>
     */
    public function logout(Request $request): array
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
     * Each signature can only be used once — a Redis SET NX EX 300
     * key prevents replay within the timestamp window.
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

            return redirect('index.php');
        }

        // Validate timestamp is within ±5 minutes
        $now = time();
        if (abs($now - $timestamp) > 300) {
            Logger::writeWithContext((string) sprintf('passkeyLogin: timestamp out of window (server=%d, client=%d)', $now, $timestamp), (string) 'warning', (bool) false);

            return redirect('index.php');
        }

        // Replay protection: use Cache::add() which is atomic "set if not exists".
        // If the key already exists, the signature has been used before — reject.
        $replayKey = 'passkey_login_used:'.hash('sha256', $signature);
        $stored = Cache::add($replayKey, '1', now()->addSeconds(300));
        if ($stored === false) {
            Logger::writeWithContext((string) 'passkeyLogin: replay detected — signature already used', (string) 'warning', (bool) false);

            return redirect('index.php');
        }

        if ($deadline && $deadline > now()->toDateTimeString()) {
            $user = User::query()->where('passkey', $passkey)->first(['id', 'passhash', 'secret', 'auth_key']);
            if ($user) {
                $ip = Network::clientIp();
                AuthCookie::setLoginCookie((int) $user->id, (string) $user->auth_key, (int) 0);
                $user->last_login = now();
                $user->save();
                $this->userRepository->saveLoginLog($user->id, $ip, 'Passkey', false);
            }
        }

        return redirect('index.php');
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
            return redirect('index.php');
        }

        $deadline = SiteConfig::current()->security->loginSecretDeadline();
        if ($deadline && $deadline > now()->toDateTimeString()) {
            $user = User::query()->where('passkey', $passkey)->first(['id', 'passhash', 'secret', 'auth_key']);
            if ($user) {
                $ip = Network::clientIp();
                AuthCookie::setLoginCookie((int) $user->id, (string) $user->auth_key, (int) 0);
                $user->last_login = now();
                $user->save();
                $this->userRepository->saveLoginLog($user->id, $ip, 'Passkey', false);
            }
        }

        return redirect('index.php');
    }

    /**
     * @return array<string, mixed>
     */
    public function challenge(ChallengeRequest $request): array
    {
        try {
            $username = $request->username;
            $challenge = Token::randomHex((int) 20);
            Cache::put(Token::challengeKey($username), $challenge, 300);
            $user = User::query()->where('username', $username)->first(['secret', 'passhash_algo']);

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
