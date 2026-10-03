<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function getList(array $params);

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function getBase($id);

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function getDetail($id, Authenticatable $currentUser);

    /**
     * @param  array<int|string, mixed>  $params
     * @return User
     */
    public function store(array $params);

    /**
     * @param  mixed  $id
     * @param  mixed  $password
     * @param  mixed  $passwordConfirmation
     * @return mixed
     */
    public function resetPassword($id, $password, $passwordConfirmation);

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function getInviteInfo($id);

    /**
     * @param  mixed  $uid
     * @param  mixed  $metaKeys
     * @param  mixed  $valid
     * @return mixed
     */
    public function listMetas($uid, $metaKeys = [], $valid = true);

    /**
     * @param  mixed  $uid
     * @param  array<int|string, mixed>  $params
     */
    public function consumeBenefit($uid, array $params): bool;

    /**
     * @param  mixed  $user
     * @param  array<string, mixed>  $metaData
     * @param  array<string, mixed>  $keyExistsUpdates
     * @param  mixed  $notify
     * @return mixed
     */
    public function addMeta($user, array $metaData, array $keyExistsUpdates = [], $notify = true);

    /**
     * @return mixed
     */
    public function saveLoginLog(int $uid, string $ip, string $client = '', bool $notify = false);

    /**
     * @return User|null
     */
    public function findForCacheClear(string|int $id);

    public function findForDisplay(string|int $id): ?User;

    /**
     * @return void
     */
    public function logModify(string|int $userId, string $comment);

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $columns
     * @return Collection<int, User>
     */
    public function getByIds(array $ids, array $columns = []): Collection;

    public function findById(int $id): ?User;

    public function existsById(int $id): bool;

    /**
     * @param  list<string>  $columns
     */
    public function findByUsername(string $username, array $columns = ['*']): ?User;

    /**
     * @param  list<string>  $columns
     */
    public function findByEmail(string $email, array $columns = ['*']): ?User;

    /**
     * @param  list<string>  $columns
     */
    public function findByPasskey(string $passkey, array $columns = ['*']): ?User;

    /**
     * @param  array<string, mixed>  $fields
     */
    public function updateFields(int $id, array $fields): void;
}
