<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Auth\NexusWebGuard;
use App\Support\AuthCookie;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

/**
 * Panel login backed by NexusWebGuard: the stock Filament page talks to the
 * provider with cookie credentials (c_secure_pass), which can never match a
 * username/password form. Here the guard's attempt() does real password
 * verification and issues the tracker login cookie on success.
 */
class Login extends \Filament\Auth\Pages\Login
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var NexusWebGuard $guard */
        $guard = Filament::auth();
        $credentials = [
            'username' => (string) ($data['username'] ?? ''),
            'password' => (string) ($data['password'] ?? ''),
        ];
        $remember = $data['remember'] ?? false;

        $attempted = app(Timebox::class)->call(function () use ($guard, $credentials, $remember): bool {
            $this->fireAttemptingEvent($guard, $credentials, $remember);

            return $guard->attempt($credentials, $remember);
        }, (int) config('auth.timebox_duration', 200_000));

        $user = $attempted ? $guard->user() : null;

        if (! $user instanceof Authenticatable) {
            $this->fireFailedEvent($guard, null, $credentials);
            $this->throwFailureValidationException();
        }

        if (! $this->isUserAllowedToAccessPanel($user)) {
            $guard->logout();
            $this->fireFailedEvent($guard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        AuthCookie::queueLoginCookie((int) $user->getAuthIdentifier(), $remember ? 5 * 365 * 86400 : 0);

        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getUsernameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getUsernameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label(__('Username'))
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabIndex' => 1]);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
