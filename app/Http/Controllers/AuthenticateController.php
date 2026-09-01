<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\AmmdsApproveRequest;
use App\Http\Requests\Auth\ChallengeRequest;
use App\Http\Requests\Auth\IyuuApproveRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\NasToolsApproveRequest;
use App\Http\Requests\Auth\PasskeyLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\AuthenticateRepository;
use App\Repositories\UserRepository;
use App\Support\AuthCookie;
use App\Support\Config\SiteConfig;
use App\Support\Json;
use App\Support\Logger;
use App\Support\Network;
use App\Support\Token;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AuthenticateController extends Controller
{
    private AuthenticateRepository $repository;

    private UserRepository $userRepository;

    public function __construct(AuthenticateRepository $repository, UserRepository $userRepository)
    {
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
     * @return array<string, mixed>
     */
    public function nasToolsApprove(NasToolsApproveRequest $request): array
    {
        try {
            $user = $this->repository->nasToolsApprove($request->data);
            $resource = new UserResource($user);

            // temporarily compatible
            return $this->success($this->polyfillArray($resource, $request), 'Please use data.data');
        } catch (\Exception $exception) {
            $msg = $exception->getMessage();
            $params = $request->all();
            Logger::writeWithContext((string) sprintf('nasToolsApprove fail: %s, params: %s', $msg, Json::encode($params)), (string) 'info', (bool) false);

            return $this->fail($params, $msg);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function polyfillArray(JsonResource $resource, Request $request): array
    {
        $data = $resource->response($request)->getData(true)['data'];
        $result = $data;
        $result['data'] = $data;

        return $result;
    }

    public function iyuuApprove(IyuuApproveRequest $request): JsonResponse
    {
        try {
            $this->repository->iyuuApprove($request->token, $request->id, $request->verity);

            return response()->json(['success' => true]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'msg' => $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function ammdsApprove(AmmdsApproveRequest $request): array
    {
        try {
            $user = $this->repository->ammdsApprove($request);
            $resource = new UserResource($user);

            // temporarily compatible
            return $this->success($this->polyfillArray($resource, $request), 'Please use data.data');
        } catch (\Exception $exception) {
            $msg = $exception->getMessage();
            $params = $request->all();
            Logger::writeWithContext((string) sprintf('ammdsApprove fail: %s, params: %s', $msg, Json::encode($params)), (string) 'info', (bool) false);

            return $this->fail($params, $msg);
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
            Cache::put(Token::challengeKey($username), $challenge, 300);
            $user = User::query()->where('username', $username)->first(['secret', 'passhash_algo']);

            return $this->success([
                'challenge' => $challenge,
                'secret' => $user->secret ?? Token::randomHex((int) 20),
                'passhash_algo' => $user->passhash_algo ?? 'sha256',
            ]);
        } catch (\Exception $exception) {
            $msg = $exception->getMessage();
            $params = $request->all();
            Logger::writeWithContext((string) sprintf('challenge fail: %s, params: %s', $msg, Json::encode($params)), (string) 'info', (bool) false);

            return $this->fail($params, $msg);
        }
    }
}
