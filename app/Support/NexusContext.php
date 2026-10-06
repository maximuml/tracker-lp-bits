<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Per-request value object that holds all legacy runtime state.
 *
 * The context is populated from a Laravel Request and then read by the
 * SupportContext facade and legacy helpers. No PHP superglobals or $GLOBALS
 * are used after the initial Request capture.
 */
final class NexusContext
{
    public ?Request $laravelRequest = null;

    /** @var array<string, mixed>|null */
    public ?array $user = null;

    /** @var array<string, mixed> */
    public array $server = [];

    /** @var array<string, mixed> */
    public array $cookie = [];

    /** @var array<string, mixed> */
    public array $get = [];

    /** @var array<string, mixed> */
    public array $userUpdateSet = [];

    public string $langDir = '';

    public string $keyShortcutScript = '';

    public string $menuHtml = '';

    public string $menuSelected = '';

    /** @var array<string, mixed>|null */
    public ?array $viewSettings = null;

    public function setFromRequest(Request $request): void
    {
        $this->laravelRequest = $request;
        $this->server = $request->server->all();
        $this->cookie = $request->cookies->all();
        $this->get = $request->query->all();

    }

    /** @param array<string, mixed>|null $user */
    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    /** @return array<string, mixed>|null */
    public function getUser(): ?array
    {
        return $this->user;
    }

    /** @return array<string, mixed> */
    public function &getUserUpdateSet(): array
    {
        return $this->userUpdateSet;
    }

    public function addUserUpdate(string $key, mixed $value): void
    {
        $this->userUpdateSet[$key] = $value;
    }

    public function getServerValue(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->server)) {
            return $this->server[$key];
        }

        if ($this->laravelRequest !== null) {
            $value = $this->laravelRequest->server->get($key);
            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }

    public function getCookieValue(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $this->cookie)) {
            $value = $this->cookie[$key];
        } elseif ($this->laravelRequest !== null) {
            $value = $this->laravelRequest->cookies->get($key);
        } else {
            $value = $default;
        }

        if (! isset($value)) {
            $value = $default;
        }

        return is_string($value) || $value === null ? $value : (string) $value;
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->get)) {
            return $this->get[$key];
        }

        if ($this->laravelRequest !== null) {
            return $this->laravelRequest->query($key, $default);
        }

        return $default;
    }
}
