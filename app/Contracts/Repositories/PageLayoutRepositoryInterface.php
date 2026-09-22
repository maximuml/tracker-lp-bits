<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface PageLayoutRepositoryInterface
{
    public function getInboxCount(int $userId): int;

    public function getOutboxCount(int $userId): int;

    public function getConnectable(int $userId): ?int;

    /**
     * @return array{seed: int, leech: int}
     */
    public function getActivePeerCounts(int $userId): array;

    public function getUnreadMessageCount(int $userId): int;

    public function getUnreadNewsCount(?string $lastHome): int;

    public function getTotalReports(): int;

    public function getTotalCheaters(): int;

    public function getTorrentApprovalNoneCount(): int;

    public function getOpenComplaintsCount(): int;

    public function getOpenReportsCount(): int;

    public function getOpenCheatersCount(): int;

    public function getPendingInviteCount(int $userId): int;

    /**
     * @param  array<string, mixed>  $data
     * @return void
     */
    public function updateUser(int $userId, array $data);

    /**
     * @return void
     */
    public function prepareAccess();

    /**
     * @return void
     */
    public function flushAccess();
}
