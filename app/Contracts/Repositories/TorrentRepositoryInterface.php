<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Http\Request;

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
}
