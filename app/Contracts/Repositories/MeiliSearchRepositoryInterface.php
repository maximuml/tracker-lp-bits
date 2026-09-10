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

    public function import();

    public function getRequiredFields(): array;

    public function doImportFromDatabase($id = null, $index = null);

    public function search(array $params, $user);

    public function autocomplete(string $query, int $limit, User $user): array;

    public function getIndex(): Indexes;

    public function formatValueForMeili($field, $value);

    public function deleteDocuments($id);
}
