<?php

namespace App\Auth;

use App\Models\User;
use App\Services\WebAuthService;
use App\Support\AuthCookie;
use App\Support\CurrentUser;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Throwable;

class NexusWebGuard implements StatefulGuard
{
    use GuardHelpers;

    /**
     * The request instance.
     *
     * @var Request
     */
    protected $request;

    /**
     * Create a new authentication guard.
     *
     * @return void
     */
    public function __construct(Request $request, UserProvider $provider)
    {
        $this->request = $request;
        $this->provider = $provider;
    }

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?User
    {
        if ($this->user instanceof User) {
            return $this->user;
        }

        $credentials = $this->request->cookies->all();

        if ($this->validate($credentials)) {
            $user = $this->provider->retrieveByCredentials($credentials);
            if ($user instanceof User && $this->provider->validateCredentials($user, $credentials)) {
                $user->checkIsNormal();
                $this->user = $user;

                return $user;
            }
        }

        return null;
    }

    /**
     * Validate a user's credentials.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        $required = ['c_secure_pass'];
        foreach ($required as $value) {
            if (empty($credentials[$value])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function attempt(array $credentials = [], $remember = false): bool
    {
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');

        if ($username === '' || $password === '') {
            return false;
        }

        $user = User::query()->where('username', $username)->first();

        if (! $user instanceof User) {
            return false;
        }

        if (! app(WebAuthService::class)->validatePassword($user, $password)) {
            return false;
        }

        try {
            $user->checkIsNormal(['status', 'enabled']);
        } catch (Throwable $e) {
            return false;
        }

        $this->login($user, $remember);

        return true;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function once(array $credentials = []): bool
    {
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');

        if ($username === '' || $password === '') {
            return false;
        }

        $user = User::query()->where('username', $username)->first();

        if (! $user instanceof User) {
            return false;
        }

        if (! app(WebAuthService::class)->validatePassword($user, $password)) {
            return false;
        }

        try {
            $user->checkIsNormal(['status', 'enabled']);
        } catch (Throwable $e) {
            return false;
        }

        $this->setUser($user);

        return true;
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        if (! $user instanceof User) {
            return;
        }

        $this->setUser($user);

        $duration = $remember ? 5 * 365 * 86400 : 0;
        AuthCookie::setLoginCookie((int) $user->getAuthIdentifier(), null, $duration);

        app(CurrentUser::class)->set($user->toArray());
    }

    public function loginUsingId($id, $remember = false): Authenticatable|false
    {
        $user = User::find($id);

        if (! $user instanceof User) {
            return false;
        }

        $this->login($user, $remember);

        return $user;
    }

    public function onceUsingId($id): Authenticatable|false
    {
        $user = User::find($id);

        if (! $user instanceof User) {
            return false;
        }

        $this->setUser($user);

        return $user;
    }

    public function viaRemember(): bool
    {
        return false;
    }

    public function logout(): void
    {
        AuthCookie::clear();
        $this->user = null;
        app(CurrentUser::class)->set(null);
    }
}
