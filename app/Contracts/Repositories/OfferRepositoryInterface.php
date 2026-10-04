<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface OfferRepositoryInterface
{
    /**
     * @param  list<string>  $columns
     */
    public function findOffer(int $id, array $columns = ['*']): ?Offer;

    public function findOfferWithUser(int $id): ?Offer;

    public function findOfferWithVotes(int $id): ?Offer;

    public function offerNameExists(string $name): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createOffer(array $data): int;

    public function getOfferOwner(int $id): ?int;

    public function getOfferName(int $id): ?string;

    public function allowOffer(int $offerId, string $allowedTime): bool;

    public function denyOffer(int $offerId): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateOffer(int $offerId, array $data): bool;

    public function deleteOffer(int $offerId): bool;

    public function addStaffMessage(int $senderId, string $senderName, string $offerName, int $offerId): void;

    public function getUsername(int $userId): ?string;

    /**
     * @return array{count: int, rows: Collection<int, \stdClass>}
     */
    public function getLegacyList(int $category, int $offerorId, string $search, string $sort, string $direction, int $offset, int $perPage): array;

    /**
     * @return array<string, mixed>
     */
    public function list(Request $request): array;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Offer>
     */
    public function listAllowedForUser(int $userId): \Illuminate\Database\Eloquent\Collection;

    /**
     * @return array<string, int>
     */
    public function pluckNotAllowedAddedBefore(string $before): array;

    /**
     * @return array<string, int>
     */
    public function pluckAllowedBefore(string $before): array;

    /**
     * @param  array<int>  $ids
     */
    public function deleteMany(array $ids): int;

    public function findOrFailById(int $id): Offer;
}
