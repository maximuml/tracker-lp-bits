<?php
namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class NexusWebUserProvider implements UserProvider
{
    /**
     * @var \Illuminate\Database\Eloquent\Builder<User>
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
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
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
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {

    }


    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array<string, mixed>  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $user = \App\Support\AuthCookie::userFromCookie($credentials, false);
        return $user instanceof User ? $user : null;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array<string, mixed>  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (! $user instanceof User) {
            return false;
        }

        return \App\Support\AuthCookie::verifyToken(
            (string) ($credentials['c_secure_pass'] ?? ''),
            (string) $user->auth_key,
        ) !== null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        // TODO: Implement rehashPasswordIfRequired() method.
    }
}
