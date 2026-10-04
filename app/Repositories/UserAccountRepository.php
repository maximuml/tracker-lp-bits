<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Misc users-table access that does not belong to a dedicated domain
 * repository: login/recovery finds, existence checks, account creation and
 * generic column updates.
 */
final class UserAccountRepository extends BaseRepository
{
    /** @param  array<int, string>  $fields */
    public function findByIdFields(int $id, array $fields): ?User
    {
        return User::query()->find($id, $fields);
    }

    /** @param  array<int, string>  $fields */
    public function findOrFailByIdFields(int $id, array $fields): User
    {
        return User::query()->findOrFail($id, $fields);
    }

    /**
     * The credential columns the login flow reads before verifying the
     * password.
     */
    public function findForLogin(string $username): ?User
    {
        return User::query()
            ->where('username', $username)
            ->first(['id', 'username', 'passhash', 'passhash_algo', 'secret', 'auth_key', 'enabled', 'status', 'two_step_secret', 'lang']);
    }

    /** @param  array<int, string>  $fields */
    public function findByEmail(string $email, array $fields = ['*']): ?User
    {
        return User::query()->where('email', $email)->first($fields);
    }

    public function findDisabledByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->where('enabled', false)->first();
    }

    public function existsByEmail(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    public function existsByUsername(string $username): bool
    {
        return User::query()->where('username', $username)->exists();
    }

    public function findOrFailById(int $id): User
    {
        return User::query()->findOrFail($id);
    }

    public function countUsers(): int
    {
        return User::query()->count();
    }

    public function countByIp(string $ip): int
    {
        return User::query()->where('ip', $ip)->count();
    }

    public function existsConfirmedById(int $id): bool
    {
        return User::query()->where('id', $id)->where('status', UserStatus::CONFIRMED->value)->exists();
    }

    /** @param  array<int, string>  $fields */
    public function findConfirmedById(int $id, array $fields, bool $lockForUpdate = false): ?User
    {
        $query = User::query()
            ->where('id', $id)
            ->where('status', UserStatus::CONFIRMED->value);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first($fields);
    }

    /** @param  array<string, mixed>  $attributes */
    public function createUser(array $attributes): User
    {
        $id = User::query()->insertGetId($attributes);

        return User::query()->findOrFail($id);
    }

    /** @param  array<string, mixed>  $fields */
    public function updateById(int $id, array $fields): int
    {
        return User::query()->where('id', $id)->update($fields);
    }

    /**
     * CAS update gated on the current status (e.g. confirm only while
     * still PENDING).
     *
     * @param  array<string, mixed>  $fields
     */
    public function updateByIdAndStatus(int $id, int|string $status, array $fields): int
    {
        return User::query()->where('id', $id)->where('status', $status)->update($fields);
    }

    public function incrementAuthVersion(int $id): int
    {
        return User::query()->where('id', $id)->increment('auth_version');
    }

    /**
     * Compare-and-swap seedbonus increment: only applies while the stored
     * value still equals $expectedBonus (guards against concurrent updates).
     */
    public function incrementSeedBonusForExpected(int $id, int|float|string|null $expectedBonus, int|float $amount): int
    {
        return User::query()
            ->where('id', $id)
            ->where('seedbonus', $expectedBonus)
            ->increment('seedbonus', $amount);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @param  array<int, string>  $fields
     * @return EloquentCollection<int, User>
     */
    public function listByIds(array $ids, array $fields): EloquentCollection
    {
        return User::query()->whereIn('id', $ids)->get($fields);
    }

    /**
     * User ids whose username is in $names (MeiliSearch owner filter).
     *
     * @param  array<int, string>  $names
     * @return Collection<int, int>
     */
    public function pluckIdsByUsernames(array $names): Collection
    {
        return User::query()->whereIn('username', $names)->pluck('id');
    }

    /**
     * Partial-username match for the MeiliSearch owner filter (LIKE
     * %term%, same semantics as the SQL search path).
     *
     * @return array<int, int>
     */
    public function pluckIdsByUsernameLike(string $term): array
    {
        return User::query()
            ->where('username', 'LIKE', '%'.$term.'%')
            ->pluck('id')
            ->all();
    }
}
