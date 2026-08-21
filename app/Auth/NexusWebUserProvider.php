<?php

namespace App\Auth;

use App\Models\User;
use App\Support\AuthCookie;
use App\Support\PasswordHasher;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Eloquent\Builder;

class NexusWebUserProvider implements UserProvider
{
    /**
     * @var Builder<User>
     */
    protected $query;

    public function __construct()
    {
        $this->query = User::query();
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $user = $this->query->where('id', $identifier)->first();

        return $user instanceof User ? $user : null;
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
            (string) $user->auth_key,
        );

        return $payload !== null && $payload['user_id'] === $user->id;
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
            User::query()->where('id', $user->id)->update([
                'passhash' => PasswordHasher::hash($password),
                'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
            ]);
        }
    }
}
