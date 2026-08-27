<?php

namespace App\Http\Controllers;

use App\Exceptions\NexusException;
use App\Http\Resources\UserResource;
use App\Models\OauthProvider;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\AuthCookie;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Url;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OauthController extends Controller
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * client redirect to authorization server, use oauth_providers config
     */
    public function redirect(Request $request, string $uuid): RedirectResponse
    {
        $provider = OauthProvider::query()->where('uuid', $uuid)->firstOrFail();
        $request->session()->put('state', $state = Str::random(40));

        $query = http_build_query([
            'client_id' => $provider->client_id,
            'redirect_uri' => $this->getCallbackUrl($provider),
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
        ]);
        $authorizationUrl = sprintf(
            '%s%s%s',
            $provider->authorization_endpoint_url,
            str_contains($provider->authorization_endpoint_url, '?') ? '&' : '?',
            $query
        );

        return redirect($authorizationUrl);

    }

    private function getCallbackUrl(OauthProvider $provider): string
    {
        return OauthProvider::getCallbackUrl($provider->uuid);
    }

    /**
     * authorization server redirect to this url with auth code after user authorized
     * and then use auth code to request to authorization server token endpoint url to get access token
     *
     * @throws ConnectionException
     * @throws \Throwable
     */
    public function callback(Request $request, string $uuid): RedirectResponse
    {
        $state = $request->session()->pull('state');

        throw_unless(
            strlen($state) > 0 && $state === $request->state,
            \InvalidArgumentException::class
        );

        $provider = OauthProvider::query()->where('uuid', $uuid)->firstOrFail();

        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'redirect_uri' => $this->getCallbackUrl($provider),
            'code' => $request->code,
        ];
        $response = Http::asForm()->post($provider->token_endpoint_url, $params);
        $tokenInfo = $response->json();
        if (empty($tokenInfo['access_token'])) {
            Logger::writeWithContext((string) ('Get tokenInfo with: '.json_encode($params).' error: '.$response->body()), (string) 'error', (bool) false);
            throw new NexusException(Locale::trans('oauth.get_access_token_error', ['error' => $tokenInfo['error'] ?? ''], null));
        }
        // use token get user-info
        $response = Http::withToken($tokenInfo['access_token'])->get($provider->user_info_endpoint_url);
        $userInfo = $response->json();
        Logger::writeWithContext((string) ('userInfo: '.$response->body()), (string) 'info', (bool) false);
        $homeUrl = Url::schemeAndHost(false).'/index.php';
        $providerUserId = data_get($userInfo, $provider->id_claim);
        if (empty($providerUserId)) {
            throw new NexusException(Locale::trans('oauth.get_provider_user_id_error', ['id_claim' => $provider->id_claim], null));
        }
        $socialAccount = SocialAccount::query()
            ->where('provider_id', $provider->id)
            ->where('provider_user_id', $providerUserId)
            ->first();
        if ($socialAccount) {
            // already bind, login directly
            /**
             * @var User $authUser
             */
            $authUser = $socialAccount->user;
            $authUser->checkIsNormal();
            AuthCookie::setLoginCookie((int) $authUser->id, (string) $authUser->auth_key, (int) 0);

            return redirect($homeUrl);
        }
        $providerEmail = data_get($userInfo, $provider->email_claim);
        if (empty($providerEmail)) {
            throw new NexusException(Locale::trans('oauth.get_provider_email_error', ['email_claim' => $provider->email_claim], null));
        }
        $sameEmailUser = User::query()->where('email', $providerEmail)->first();
        if ($sameEmailUser) {
            // login to bind is better, not implement this time
            throw new NexusException(Locale::trans('oauth.provider_email_already_exists', ['email' => $providerEmail], null));
        }
        $providerUsername = data_get($userInfo, $provider->username_claim);
        $providerLevel = data_get($userInfo, $provider->level_claim);

        $minLevel = $provider->level_limit;
        if ($minLevel) {
            if (! $providerLevel) {
                throw new NexusException(Locale::trans('oauth.get_provider_level_error', ['level_claim' => $provider->level_claim], null));
            }
            if ($providerLevel < $minLevel) {
                throw new NexusException(Locale::trans('oauth.provider_level_not_allowed', ['level_limit' => $provider->level_limit], null));
            }
        }

        $newUser = $this->createUser($providerUsername, $providerEmail, $provider->id);
        $socialAccountData = [
            'user_id' => $newUser->id,
            'provider_id' => $provider->id,
            'provider_user_id' => $providerUserId,
            'provider_username' => $providerUsername,
            'provider_email' => $providerEmail,
        ];
        SocialAccount::query()->create($socialAccountData);
        Logger::writeWithContext((string) sprintf('newUser: %s, socialAccount: %s', json_encode($newUser), json_encode($socialAccountData)), (string) 'info', (bool) false);
        AuthCookie::setLoginCookie((int) $newUser->id, (string) $newUser->auth_key, (int) 0);

        return redirect($homeUrl);
    }

    /**
     * @param  mixed  $username
     * @param  mixed  $email
     * @param  mixed  $providerId
     */
    private function createUser($username, $email, $providerId): User
    {
        if ($username) {
            if (User::query()->where('username', $username)->exists()) {
                // already in use
                $username .= Str::random(2);
            }
        } else {
            $username = $email;
        }
        $password = Str::random(10);
        $userData = [
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'provider_id' => $providerId,
        ];
        $userRep = $this->userRepository;
        for ($i = 0; $i < 3; $i++) {
            $userData['username'] = $username;
            try {
                return $userRep->store($userData);
            } catch (\Throwable $e) {
                Logger::writeWithContext((string) $e->getMessage(), (string) 'error', (bool) false);
            }
            $username = Str::random(2).$username;
        }
        throw new NexusException('Unable to create user');
    }

    /** @return  array<int|string, mixed> */
    public function userInfo(): array
    {
        $user = Auth::user();
        $resource = new UserResource($user);

        return $resource->response()->getData(true)['data'];
    }
}
