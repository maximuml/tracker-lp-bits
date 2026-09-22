<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;

interface MeiliSearchRepositoryInterface
{
    public function getClient(): Client;

    public function isEnabled(): bool;

    /**
     * @return mixed
     */
    public function import();

    /**
     * @return array<int|string, mixed>
     */
    public function getRequiredFields(): array;

    /**
     * @param  mixed  $id
     * @param  mixed  $index
     * @return mixed
     */
    public function doImportFromDatabase($id = null, $index = null);

    /**
     * @param  array<int|string, mixed>  $params
     * @param  mixed  $user
     * @return mixed
     */
    public function search(array $params, $user);

    /**
     * @return array<int, array<string, mixed>>
     */
    public function autocomplete(string $query, int $limit, User $user): array;

    public function getIndex(): Indexes;

    /**
     * @param  mixed  $field
     * @param  mixed  $value
     * @return mixed
     */
    public function formatValueForMeili($field, $value);

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function deleteDocuments($id);
}
