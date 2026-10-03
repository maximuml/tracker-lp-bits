<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface AttachmentRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByDlkey(string $dlkey): ?array;

    /**
     * @param  array<int, string>  $dlkeys
     * @return array<string, array<string, mixed>>
     */
    public function findByDlkeys(array $dlkeys): array;

    public function countRecentForUser(int $userId): int;
}
