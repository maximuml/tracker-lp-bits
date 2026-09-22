<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

interface TagRepositoryInterface
{
    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function getList(array $params);

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function store(array $params);

    /**
     * @param  array<int|string, mixed>  $params
     * @param  mixed  $id
     * @return mixed
     */
    public function update(array $params, $id);

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function getDetail($id);

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function delete($id);

    /**
     * @return mixed
     */
    public function createBasicQuery();

    /**
     * @param  array<int|string, mixed>  $checked
     * @param  mixed  $ignorePermission
     */
    public function renderCheckbox(int $searchBoxId, array $checked = [], $ignorePermission = false): string;

    /**
     * @param  array<int|string, mixed>  $renderIdArr
     * @param  mixed  $withFilterLink
     */
    public function renderSpan(int $searchBoxId, array $renderIdArr = [], $withFilterLink = false): string;

    /**
     * @return mixed
     */
    public function migrateTorrentTag();

    public function getOrderByFieldIdString(): string;

    /**
     * @param  array<int, int>  $tagIdArr
     * @return void
     */
    public function syncTorrentTags(string|int $torrentId, array $tagIdArr, bool $sync = false);

    /** @return Collection<int, Tag> */
    public function listAll(int $searchBoxId = 0): Collection;

    /**
     * @param  mixed  $name
     * @param  mixed  $value
     */
    public function buildSelect(int $searchBoxId, $name, $value): string;
}
