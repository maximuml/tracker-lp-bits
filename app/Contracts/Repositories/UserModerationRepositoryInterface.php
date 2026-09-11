<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserModerationRepositoryInterface
{
    public function disableUser(User $operator, $uid, $reason = '');

    public function enableUser(User $operator, $uid, $reason = '');

    public function getModComment(int $id);

    public function incrementDecrement(User $operator, $uid, $action, $field, $value, $reason = ''): bool;

    public function removeLeechWarn($operator, $uid): bool;

    public function removeTwoStepAuthentication($operator, $uid): bool;

    public function updateDownloadPrivileges($operator, $user, bool $status, $disableReasonKey = null);

    public function updateUploadPrivileges($operator, $user, bool $status);

    public function updateForumPost($operator, $user, bool $status);

    public function warnUser($operator, $user, int $weeks, string $reason = '');

    public function removeWarnings(User $operator, array $userIds): void;

    public function changeClass($operator, $targetUser, $newClass, $reason = '', array $extra = []): bool;

    public function destroy(Collection|int $id, $reasonKey = 'user.destroy_by_admin');

    public function confirmUser($id): bool;

    public function addTemporaryInvite(?User $operator, int $uid, string $action, int $count, ?int $days, ?string $reason = '');

    public function getInviteBtnText(int $uid);
}
