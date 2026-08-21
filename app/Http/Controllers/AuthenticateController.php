<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Nexus\Database\NexusDB;

class AuthenticateController extends Controller
{
    /** @var mixed */
    private $repository;

    /**
     * @return mixed
     */
    public function __construct(AuthenticateRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<string, mixed>
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);
        $result = $this->repository->login($request->username, $request->password);
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
    public function logout(Request $request)
    {
        $result = $this->repository->logout(Auth::id());

        return $this->success($result);
    }

    /**
     * @param  mixed  $passkey
     * @return mixed
     */
    public function passkeyLogin($passkey)
    {
        $deadline = SiteConfig::current()->security->loginSecretDeadline();
        if ($deadline && $deadline > now()->toDateTimeString()) {
            $user = User::query()->where('passkey', $passkey)->first(['id', 'passhash', 'secret', 'auth_key']);
            if ($user) {
                $ip = Network::clientIp();
                AuthCookie::setLoginCookie((int) $user->id, (string) $user->auth_key, (int) 0);
                $user->last_login = now();
                $user->save();
                $userRep = new UserRepository;
                $userRep->saveLoginLog($user->id, $ip, 'Passkey', false);
            }
        }

        return redirect('index.php');
    }

    /**
     * @return array<string, mixed>
     */
    public function nasToolsApprove(Request $request)
    {
        $request->validate([
            'data' => 'required|string',
        ]);
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
     * @return mixed
     */
    private function polyfillArray(JsonResource $resource, Request $request)
    {
        $data = $resource->response($request)->getData(true)['data'];
        $result = $data;
        $result['data'] = $data;

        return $result;
    }

    /**
     * @return mixed
     */
    public function iyuuApprove(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'id' => 'required|integer',
                'verity' => 'required|string',
                'provider' => ['required', 'string', Rule::in('iyuu')],
            ]);
            $this->repository->iyuuApprove($request->token, $request->id, $request->verity);

            return response()->json(['success' => true]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'msg' => $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function ammdsApprove(Request $request)
    {
        try {
            $request->validate([
                'uid' => 'required|integer',
                'timestamp' => 'required|integer',
                'nonce' => 'required|string',
                'signature' => 'required|string',
            ]);
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
    public function challenge(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
            ]);
            $username = $request->username;
            $challenge = Token::randomHex((int) 20);
            NexusDB::cache_put(Token::challengeKey($username), $challenge, 300);
            $user = User::query()->where('username', $username)->first(['secret']);

            return $this->success([
                'challenge' => $challenge,
                'secret' => $user->secret ?? Token::randomHex((int) 20),
            ]);
        } catch (\Exception $exception) {
            $msg = $exception->getMessage();
            $params = $request->all();
            Logger::writeWithContext((string) sprintf('challenge fail: %s, params: %s', $msg, Json::encode($params)), (string) 'info', (bool) false);

            return $this->fail($params, $msg);
        }
    }
}
