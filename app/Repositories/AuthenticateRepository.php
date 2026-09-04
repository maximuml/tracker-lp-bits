<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\WebAuthService;
use App\Support\TwoFactorAuthHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuthenticateRepository extends BaseRepository
{
    /**
     * @param  mixed  $username
     * @param  mixed  $password
     * @return mixed
     */
    public function login($username, $password, string $twoStepCode = '')
    {
        $user = User::query()
            ->where('username', (string) $username)
            ->first(array_merge(User::$commonFields, ['class', 'secret', 'passhash', 'auth_key', 'passhash_algo', 'two_step_secret']));
        if (! $user instanceof User || ! app(WebAuthService::class)->validatePassword($user, $password)) {
            throw new \InvalidArgumentException('Username or password invalid.');
        }
        $user->checkIsNormal();
        if (! empty($user->two_step_secret)) {
            if ($twoStepCode === '' || ! TwoFactorAuthHelper::verifyCode((string) $user->two_step_secret, $twoStepCode)) {
                throw new \InvalidArgumentException($twoStepCode === '' ? 'Require two-step code.' : 'Invalid two-step code.');
            }
        }
        $tokenName = __METHOD__.__LINE__;
        $token = DB::transaction(function () use ($user, $tokenName) {
            $user->update(['last_login' => Carbon::now()]);
            $tokenResult = $user->createToken($tokenName);

            return $tokenResult->plainTextToken;
        });
        $result = (new UserResource($user))->response()->getData(true)['data'];
        $result['token'] = $token;

        return $result;
    }

    /**
     * @return mixed
     */
    public function logout(int $id)
    {
        $user = User::query()->findOrFail($id, ['id']);
        $result = $user->tokens()->delete();

        return $result;
    }
}
