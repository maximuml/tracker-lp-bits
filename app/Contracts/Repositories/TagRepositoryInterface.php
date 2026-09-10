<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;

interface TagRepositoryInterface
{
    public function getList(array $params);

    public function store(array $params);

    public function update(array $params, $id);

    public function getDetail($id);

    public function delete($id);

    public function createBasicQuery();

    public function renderCheckbox(int $searchBoxId, array $checked = [], $ignorePermission = false): string;

    public function renderSpan(int $searchBoxId, array $renderIdArr = [], $withFilterLink = false): string;

    public function migrateTorrentTag();

    public function getOrderByFieldIdString(): string;

    public function syncTorrentTags(string|int $torrentId, array $tagIdArr, bool $sync = false);

    public function listAll(int $searchBoxId = 0): Collection;

    public function buildSelect(int $searchBoxId, $name, $value): string;
}
