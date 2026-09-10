<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function getList(array $params);

    public function getBase($id);

    public function getDetail($id, Authenticatable $currentUser);

    public function store(array $params);

    public function resetPassword($id, $password, $passwordConfirmation);

    public function getInviteInfo($id);

    public function listMetas($uid, $metaKeys = [], $valid = true);

    public function consumeBenefit($uid, array $params): bool;

    public function addMeta($user, array $metaData, array $keyExistsUpdates = [], $notify = true);

    public function saveLoginLog(int $uid, string $ip, string $client = '', bool $notify = false);

    public function findForCacheClear(string|int $id);

    public function findForDisplay(string|int $id): ?User;

    public function logModify(string|int $userId, string $comment);

    public function getByIds(array $ids, array $columns = []): Collection;
}
