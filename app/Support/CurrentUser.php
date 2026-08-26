<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Per-request cache of the current user's legacy array representation.
 *
 * Replaces SupportContext::getUser()/setUser() with a container singleton
 * that reads from Laravel's Auth facade. The legacy array format (with keys
 * like 'id', 'class', 'passkey') is preserved so existing call sites that
 * use `$user['key']` access patterns continue to work.
 */
final class CurrentUser
{
    /** @var array<string, mixed>|null */
    private ?array $cached = null;

    private bool $initialized = false;

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
                $this->cached = SupportContext::getUser();
            } catch (\Throwable) {
                $this->cached = null;
            }
        }
    }
}
