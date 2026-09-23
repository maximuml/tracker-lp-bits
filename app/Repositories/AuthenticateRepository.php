<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\RoutePermissionEnum;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\WebAuthService;
use App\Support\Network;
use App\Support\TwoFactorAuthHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuthenticateRepository extends BaseRepository
{
    public function __construct(
        private readonly WebAuthService $webAuthService,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @param  mixed  $username
     * @param  mixed  $password
     * @return mixed
     */
    public function login($username, $password, string $twoStepCode = '', ?string $ip = null)
    {
        $ip ??= Network::clientIp();
        // Same contract as the web login: an IP that exhausted its
        // attempts must not keep guessing passwords through the API.
        $this->webAuthService->assertNotBanned($ip);

        $user = User::query()
            ->where('username', (string) $username)
            ->first(array_merge(User::$commonFields, ['class', 'secret', 'passhash', 'auth_key', 'passhash_algo', 'two_step_secret', 'must_change_password']));
        if (! $user instanceof User || ! $this->webAuthService->validatePassword($user, $password)) {
            $this->webAuthService->recordFailedAttempt($ip);
            throw new \InvalidArgumentException('Username or password invalid.');
        }
        try {
            $user->checkIsNormal();
        } catch (\Throwable $e) {
            $this->webAuthService->recordFailedAttempt($ip);
            throw $e;
        }
        if (! empty($user->two_step_secret)) {
            if ($twoStepCode === '' || ! TwoFactorAuthHelper::verifyCode((string) $user->two_step_secret, $twoStepCode)) {
                $this->webAuthService->recordFailedAttempt($ip);
                throw new \InvalidArgumentException($twoStepCode === '' ? 'Require two-step code.' : 'Invalid two-step code.');
            }
        }
        $tokenName = __METHOD__.__LINE__;
        $abilities = $this->tokenAbilities($user);
        $token = DB::transaction(function () use ($user, $tokenName, $abilities) {
            $user->update(['last_login' => Carbon::now()]);
            $tokenResult = $user->createToken($tokenName, $abilities);

            return $tokenResult->plainTextToken;
        });
        // Audit row always; notify=false like the passkey flow — API
        // clients re-login on every restart and would spam notifications.
        $this->userRepository->saveLoginLog((int) $user->id, $ip, 'API', false);
        $result = (new UserResource($user))->response()->getData(true)['data'];
        $result['token'] = $token;
        if ($abilities !== ['*']) {
            $result['password_change_required'] = true;
        }

        return $result;
    }

    /**
     * A user flagged must_change_password gets a limited token: it can
     * read its own profile, change the password and log out — everything
     * else waits until the password change.
     *
     * @return list<string>
     */
    private function tokenAbilities(User $user): array
    {
        if (! $user->must_change_password) {
            return ['*'];
        }

        return [
            RoutePermissionEnum::AUTH_LOGOUT->value,
            RoutePermissionEnum::USER_ME->value,
            RoutePermissionEnum::USERCP_SETTINGS->value,
        ];
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
