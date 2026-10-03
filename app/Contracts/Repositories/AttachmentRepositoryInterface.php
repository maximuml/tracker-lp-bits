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

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdAndDlkey(int $id, string $dlkey): ?array;

    public function incrementDownloads(int $id): void;

    public function countRecentForUser(int $userId): int;
}
