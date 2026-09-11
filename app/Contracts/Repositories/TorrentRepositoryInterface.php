<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Http\Request;

interface TorrentRepositoryInterface
{
    public function getList(Request $request, User $user, ?string $sectionName = null);

    public function getDetail(int $id, User $user);

    public function getSearchBox(?int $id = null);

    public function getPaidIcon(array $torrentInfo, $size = 16, $verticalAlign = 'sub');

    public function buildUploadFieldInput($name, $value, $noteText, $btnText, $btnId = '', $btnOnClick = ''): string;
}
