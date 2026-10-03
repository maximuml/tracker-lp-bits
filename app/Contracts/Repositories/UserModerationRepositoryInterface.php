<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;
use App\Models\UserBanLog;
use Illuminate\Support\Collection;

interface UserModerationRepositoryInterface
{
    /**
     * @param  mixed  $uid
     * @param  mixed  $reason
     * @return mixed
     */
    public function disableUser(User $operator, $uid, $reason = '');

    /**
     * @param  mixed  $uid
     * @param  mixed  $reason
     * @return mixed
     */
    public function enableUser(User $operator, $uid, $reason = '');

    /**
     * @return string|null
     */
    public function getModComment(int $id);

    /**
     * @param  mixed  $uid
     * @param  mixed  $action
     * @param  mixed  $field
     * @param  mixed  $value
     * @param  mixed  $reason
     */
    public function incrementDecrement(User $operator, $uid, $action, $field, $value, $reason = ''): bool;

    /**
     * @param  mixed  $operator
     * @param  mixed  $uid
     */
    public function removeLeechWarn($operator, $uid): bool;

    /**
     * @param  mixed  $operator
     * @param  mixed  $uid
     */
    public function removeTwoStepAuthentication($operator, $uid): bool;

    /**
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  mixed  $disableReasonKey
     * @return mixed
     */
    public function updateDownloadPrivileges($operator, $user, bool $status, $disableReasonKey = null);

    /**
     * @param  mixed  $operator
     * @param  mixed  $user
     * @return mixed
     */
    public function updateUploadPrivileges($operator, $user, bool $status);

    /**
     * @param  mixed  $operator
     * @param  mixed  $user
     * @return mixed
     */
    public function updateForumPost($operator, $user, bool $status);

    /**
     * @param  mixed  $operator
     * @param  mixed  $user
     * @return mixed
     */
    public function warnUser($operator, $user, int $weeks, string $reason = '');

    /**
     * @param  array<int>  $userIds
     */
    public function removeWarnings(User $operator, array $userIds): void;

    /**
     * @param  mixed  $operator
     * @param  mixed  $targetUser
     * @param  mixed  $newClass
     * @param  mixed  $reason
     * @param  array<int|string, mixed>  $extra
     */
    public function changeClass($operator, $targetUser, $newClass, $reason = '', array $extra = []): bool;

    /**
     * @param  Collection<int, mixed>|int  $id
     * @param  mixed  $reasonKey
     * @return mixed
     */
    public function destroy(Collection|int $id, $reasonKey = 'user.destroy_by_admin');

    /**
     * @param  mixed  $id
     */
    public function confirmUser($id): bool;

    /**
     * @return mixed
     */
    public function addTemporaryInvite(?User $operator, int $uid, string $action, int $count, ?int $days, ?string $reason = '');

    /**
     * @return mixed
     */
    public function getInviteBtnText(int $uid);

    public function latestBanLogForUser(int $userId): ?UserBanLog;

    public function countBanLogs(?string $usernameQuery): int;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, UserBanLog>
     */
    public function listBanLogs(?string $usernameQuery, int $offset, int $perPage): \Illuminate\Database\Eloquent\Collection;
}
