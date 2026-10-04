<?php

declare(strict_types=1);

namespace App\Auth;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Support\AuthCookie;
use App\Support\Cache;
use App\Support\PasswordHasher;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\DB;

class NexusWebUserProvider implements UserProvider
{
    public function __construct(
        private readonly AuthRepositoryInterface $authRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $user = $this->userRepository->findById((int) $identifier);

        return $user;
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token) {}

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array<string, mixed>  $credentials
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $user = AuthCookie::userFromCookie($credentials, false);

        return $user instanceof User ? $user : null;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  array<string, mixed>  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (! $user instanceof User) {
            return false;
        }

        $payload = AuthCookie::verifyToken(
            (string) ($credentials['c_secure_pass'] ?? ''),
        );

        if ($payload === null || $payload['user_id'] !== $user->id) {
            return false;
        }

        $currentVersion = $this->authRepository->getAuthVersion((int) $user->id);

        return $payload['auth_version'] !== null
            && $currentVersion !== null
            && $payload['auth_version'] === (int) $currentVersion;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        if (! $user instanceof User) {
            return;
        }

        $password = (string) ($credentials['password'] ?? '');
        if ($password === '') {
            return;
        }

        $algo = (string) ($user->passhash_algo ?? PasswordHasher::ALGO_SHA256);
        $passhash = (string) $user->passhash;

        if ($force || PasswordHasher::needsRehash($algo, $passhash)) {
            $user->makeVisible(['passhash']);
            $this->authRepository->updateLogin((int) $user->id, [
                'passhash' => PasswordHasher::hash($password),
                'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
                'must_change_password' => $algo !== PasswordHasher::ALGO_ARGON2ID,
                'auth_version' => DB::raw('auth_version + 1'),
            ]);
            Cache::clearUser((int) $user->id, '');
        }
    }
}
