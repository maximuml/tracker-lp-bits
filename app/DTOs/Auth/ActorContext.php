<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass;
use App\Exceptions\InsufficientPermissionException;
use App\Models\User;
use App\Support\CurrentUser;
use App\Support\YesNo;

/**
 * Immutable per-request actor context.
 *
 * Replaces the legacy `array $currentUser` / `$CURUSER` pattern with a
 * typed, read-only value object. Constructed once per request from the
 * authenticated User model (or the CurrentUser singleton) and injected
 * into services that need the actor's identity, class, locale, or
 * permission checks.
 *
 * The container singleton is a per-request snapshot: `CurrentUser::set()`/
 * `reset()` drop it when the legacy user changes mid-request, `ResetNexus`
 * flushes it on worker lifecycle events, and `FlushRouteController`
 * re-resolves route-memoized controllers so their constructor-injected
 * actor is built after legacy auth has run.
 */
final readonly class ActorContext
{
    /**
     * @param  list<PermissionEnum>  $permissions  Cached permission enum values for this actor.
     */
    public function __construct(
        public int $id,
        public string $username,
        public UserClass $class,
        public string $locale,
        public int $stylesheet,
        public int $page,
        public ?string $passkey,
        /** @var list<PermissionEnum> */
        public array $permissions,
        public ?User $user = null,
    ) {}

    /**
     * Build an ActorContext from the authenticated user.
     *
     * Falls back to the CurrentUser singleton (which reads from Auth)
     * when no explicit User is provided.
     */
    public static function fromAuth(?User $user = null): self
    {
        if ($user === null) {
            $cached = CurrentUser::instance()->get();
            if ($cached !== null && (int) ($cached['id'] ?? 0) > 0) {
                // CurrentUser already holds the freshly-resolved user row
                // (SiteAccess cookie check or a mid-request set() override)
                // — hydrate a model from it instead of re-querying.
                $user = (new User)->newFromBuilder($cached);
            }
        }

        if (! $user instanceof User) {
            // Guest / unauthenticated: return a minimal context
            return new self(
                id: 0,
                username: '',
                class: UserClass::PEASANT,
                locale: 'en',
                stylesheet: 0,
                page: 0,
                passkey: null,
                permissions: [],
                user: null,
            );
        }

        $class = UserClass::tryFrom((int) $user->class) ?? UserClass::PEASANT;
        $locale = (string) ($user->lang ?? 'en');
        $stylesheet = (int) ($user->stylesheet ?? 0);
        $page = (int) ($user->page ?? 0);
        $passkey = $user->passkey;

        // Pre-compute all permissions for this actor's class
        $permissions = [];
        foreach (PermissionEnum::cases() as $permission) {
            if (Permission::can($permission, $user)) {
                $permissions[] = $permission;
            }
        }

        return new self(
            id: (int) $user->id,
            username: (string) $user->username,
            class: $class,
            locale: $locale,
            stylesheet: $stylesheet,
            page: $page,
            passkey: $passkey,
            permissions: $permissions,
            user: $user,
        );
    }

    /**
     * Check whether this actor has a specific permission.
     */
    public function can(PermissionEnum $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    /**
     * Assert that this actor has a specific permission.
     *
     * @throws InsufficientPermissionException
     */
    public function assertCan(PermissionEnum $permission): void
    {
        if (! $this->can($permission)) {
            throw new InsufficientPermissionException;
        }
    }

    /**
     * Whether this actor is authenticated (id > 0).
     */
    public function isAuthenticated(): bool
    {
        return $this->id > 0;
    }

    /**
     * Whether this actor is a guest (id === 0).
     */
    public function isGuest(): bool
    {
        return $this->id === 0;
    }

    /**
     * Typed escape hatch for one-off legacy keys — mirrors
     * CurrentUser::value() so callers don't need the raw array.
     */
    public function value(string $key, mixed $default = null): mixed
    {
        return $this->toLegacyArray()[$key] ?? $default;
    }

    /**
     * Legacy enum('yes','no')/tinyint tolerant check — see YesNo.
     */
    public function yes(string $key): bool
    {
        return YesNo::isYes($this->value($key));
    }

    public function no(string $key): bool
    {
        return YesNo::isNo($this->value($key));
    }

    public function seedbonus(): float
    {
        return (float) $this->value('seedbonus', 0);
    }

    public function enabled(): bool
    {
        return YesNo::isYes($this->value('enabled', false));
    }

    /**
     * Return the legacy array representation for backward compatibility
     * with code that still expects `$currentUser['key']` access.
     *
     * @return array<string, mixed>
     */
    public function toLegacyArray(): array
    {
        if ($this->user instanceof User) {
            return $this->user->toLegacyArray();
        }

        return [
            'id' => $this->id,
            'username' => $this->username,
            'class' => $this->class->value,
            'lang' => $this->locale,
            'stylesheet' => $this->stylesheet,
            'page' => $this->page,
            'passkey' => $this->passkey ?? '',
        ];
    }
}
