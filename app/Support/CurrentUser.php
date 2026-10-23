<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\Auth\ActorContext;
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
        // Retry while guest: authentication can land after a null resolve
        // (sanctum's shouldUse, SiteAccess's set(null) on a request that
        // then authenticates out-of-band). Guards memoize their own
        // resolution, so a repeat Auth::user() is cheap.
        if ($this->cached === null) {
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
        // ActorContext is an immutable snapshot of this state — any change
        // must drop a previously resolved instance so late consumers
        // re-resolve fromAuth() with the new user.
        app()->forgetInstance(ActorContext::class);
    }

    /**
     * Seed the ambient user only when nothing has resolved yet — for
     * controllers that authenticated out-of-band (passkey, legacy
     * cookie) and want downstream legacy readers to see the actor
     * without clobbering an already-resolved user.
     */
    public function promoteIfAbsent(User $user): void
    {
        if ($this->get() === null) {
            $this->set($user->toLegacyArray());
        }
    }

    /**
     * Unconditionally promote the given user into the ambient slot —
     * the explicit seam for controllers that authenticate outside the
     * nexus-web guard.
     */
    public function promote(User $user): void
    {
        $this->set($user->toLegacyArray());
    }

    /**
     * Reset the cache (used on logout or when the user changes).
     */
    public function reset(): void
    {
        $this->cached = null;
        app()->forgetInstance(ActorContext::class);
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

    public function yes(string $key): bool
    {
        return YesNo::isYes($this->value($key));
    }

    public function no(string $key): bool
    {
        return YesNo::isNo($this->value($key));
    }

    private function initialize(): void
    {
        try {
            $user = Auth::user();
            if ($user instanceof User) {
                $this->cached = $user->toLegacyArray();

                return;
            }
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
        // No authenticated user yet — auth can still land later in the
        // pipeline (sanctum's shouldUse, an out-of-band promote()). The
        // cache stays null so the next get() retries instead of pinning a
        // guest snapshot for the whole request.
    }
}
