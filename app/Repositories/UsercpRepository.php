<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class UsercpRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->toArray();
    }
}
