<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Support\CurrentUser;

trait MakesCurrentUser
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function curUser(array $data): CurrentUser
    {
        $user = new CurrentUser;
        $user->set($data);

        return $user;
    }
}
