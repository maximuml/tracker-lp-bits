<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\DTOs\Auth\ActorContext;
use App\Enums\UserClass;
use App\Models\User;
use App\Support\CurrentUser;

trait MakesCurrentUser
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function curUser(array $data): CurrentUser
    {
        $user = new CurrentUser;
        $user->set($data);

        return $user;
    }

    /**
     * Build a test ActorContext from a legacy user array. The provided keys
     * ride through a force-filled User model so toLegacyArray()/value()
     * see every field the test supplies.
     *
     * @param  array<string, mixed>  $data
     */
    protected function actor(array $data = []): ActorContext
    {
        // Tests pass legacy 'yes'/'no' strings; the migrated schema stores
        // tinyint and the boolean casts on User would turn 'no' into true.
        foreach ($data as $key => $value) {
            if ($value === 'yes') {
                $data[$key] = 1;
            } elseif ($value === 'no') {
                $data[$key] = 0;
            }
        }

        $user = null;
        if ((int) ($data['id'] ?? 0) > 0) {
            $user = new User;
            $user->setRawAttributes($data);
        }

        return new ActorContext(
            id: (int) ($data['id'] ?? 0),
            username: (string) ($data['username'] ?? 'testuser'),
            class: UserClass::tryFrom((int) ($data['class'] ?? 0)) ?? UserClass::PEASANT,
            locale: (string) ($data['lang'] ?? 'en'),
            stylesheet: (int) ($data['stylesheet'] ?? 0),
            page: (int) ($data['page'] ?? 0),
            passkey: isset($data['passkey']) ? (string) $data['passkey'] : null,
            permissions: $data['permissions'] ?? [],
            user: $user,
        );
    }
}
