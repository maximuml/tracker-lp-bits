<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface TorrentRepositoryInterface
{
    /**
     * @return mixed
     */
    public function getList(Request $request, User $user, ?string $sectionName = null);

    /**
     * @return mixed
     */
    public function getDetail(int $id, User $user);

    /**
     * @return mixed
     */
    public function getSearchBox(?int $id = null);

    /**
     * @param  mixed  $name
     * @param  mixed  $value
     * @param  mixed  $noteText
     * @param  mixed  $btnText
     * @param  mixed  $btnId
     */
    public function buildUploadFieldInput($name, $value, $noteText, $btnText, $btnId = ''): string;

    /**
     * @param  list<string>  $columns
     */
    public function findById(int $id, array $columns = ['*']): ?Torrent;

    /**
     * @param  list<string>  $columns
     */
    public function findOrFailById(int $id, array $columns = ['*']): Torrent;

    /**
     * @param  array<string, mixed>  $fields
     */
    public function updateFields(int $id, array $fields): void;

    /**
     * @param  list<int|string>  $posStates
     * @return Collection<int, int>
     */
    public function pluckIdsByPosStates(array $posStates): Collection;

    public function getNameById(int $id): ?string;

    public function getOwnerId(int $id): ?int;
}
