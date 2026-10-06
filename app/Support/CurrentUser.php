<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Per-request cache of the current user's legacy array representation.
 *
 * Replaces NexusContext::instance()->getUser()/setUser() with a container singleton
 * that reads from Laravel's Auth facade. The legacy array format (with keys
 * like 'id', 'class', 'passkey') is preserved so existing call sites that
 * use `$user['key']` access patterns continue to work.
 */
class CurrentUser
{
    /** @var array<string, mixed>|null */
    private ?array $cached = null;

    private bool $initialized = false;

    public static function instance(): self
    {
        return app(self::class);
    }

    /**
     * Get the current user as a legacy array, or null if not logged in.
     *
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        if (! $this->initialized) {
            $this->initialize();
        }

        return $this->cached;
    }

    /**
     * Explicitly set the user array (used by auth guard and controllers
     * that need to override the authenticated user mid-request).
     *
     * @param  array<string, mixed>|null  $user
     */
    public function set(?array $user): void
    {
        $this->cached = $user;
        $this->initialized = true;
    }

    /**
     * Reset the cache (used on logout or when the user changes).
     */
    public function reset(): void
    {
        $this->cached = null;
        $this->initialized = false;
    }

    public function isLoggedIn(): bool
    {
        return $this->id() !== 0;
    }

    public function id(): int
    {
        return (int) ($this->get()['id'] ?? 0);
    }

    public function username(): string
    {
        return (string) ($this->get()['username'] ?? '');
    }

    public function classId(): int
    {
        return (int) ($this->get()['class'] ?? 0);
    }

    public function enabled(): bool
    {
        return (bool) ($this->get()['enabled'] ?? false);
    }

    public function passkey(): string
    {
        return (string) ($this->get()['passkey'] ?? '');
    }

    public function seedbonus(): float
    {
        return (float) ($this->get()['seedbonus'] ?? 0);
    }

    /**
     * Typed escape hatch for the remaining one-off keys — replaces the
     * raw `$user['key']` reads that have no dedicated accessor yet.
     */
    public function value(string $key, mixed $default = null): mixed
    {
        return $this->get()[$key] ?? $default;
    }

    private function initialize(): void
    {
        $this->initialized = true;
        try {
            $user = Auth::user();
            $this->cached = $user instanceof User ? $user->toLegacyArray() : null;
        } catch (\Throwable $e) {
            // Auth may not be available yet (e.g. during Nexus::boot()
            // before all service providers are loaded). Fall back to the
            // legacy SupportContext which is always available.
            try {
                $this->cached = NexusContext::instance()->getUser();
            } catch (\Throwable) {
                $this->cached = null;
            }
        }
    }
}
