<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

/**
 * Abuse reports (`complains` / `complain_replies` tables) for the
 * public report + staff review flows.
 */
interface ComplainRepositoryInterface
{
    public function existsByIdAndUuid(int $id, string $uuid): bool;

    /** @return list<array<string, mixed>> */
    public function listPending(): array;

    public function countAnswered(): int;

    /** @return list<array<string, mixed>> */
    public function listAnswered(int $offset, int $limit): array;

    /** @return array<string, mixed>|null */
    public function findByUuid(string $uuid): ?array;

    /** @return list<array<string, mixed>> */
    public function listReplies(int $complainId): array;
}
