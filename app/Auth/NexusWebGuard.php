<?php
namespace App\Auth;

use App\Models\User;
use App\Services\WebAuthService;
use App\Support\AuthCookie;
use App\Support\SupportContext;
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
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Create a new authentication guard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Contracts\Auth\UserProvider|null  $provider
     * @return void
     */
    public function __construct(Request $request, ?UserProvider $provider = null)
    {
        $this->request = $request;
        $this->provider = $provider;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return User|null
     */
    public function user(): ?User
    {
        if ($this->user instanceof User) {
            return $this->user;
        }

        $credentials = $this->request->cookie();

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
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        $required = ['c_secure_pass'];
        foreach ($required as $value) {
            if (empty($credentials[$value])) {
                return false;
            }
        }
        return true;
    }

    public function attempt(array $credentials = [], $remember = false)
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

    public function once(array $credentials = [])
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

    public function login(Authenticatable $user, $remember = false)
    {
        if (! $user instanceof User) {
            return;
        }

        $this->setUser($user);

        $duration = $remember ? 5 * 365 * 86400 : 0;
        AuthCookie::setLoginCookie((int) $user->getAuthIdentifier(), null, $duration);

        SupportContext::setUser($user->toArray());
    }

    public function loginUsingId($id, $remember = false)
    {
        $user = User::find($id);

        if (! $user instanceof User) {
            return false;
        }

        $this->login($user, $remember);

        return true;
    }

    public function onceUsingId($id)
    {
        $user = User::find($id);

        if (! $user instanceof User) {
            return false;
        }

        $this->setUser($user);

        return true;
    }

    public function viaRemember()
    {
        return false;
    }

    public function logout(): void
    {
        AuthCookie::clear();
        $this->user = null;
        SupportContext::setUser(null);
    }
}
