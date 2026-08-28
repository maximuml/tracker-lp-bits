<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserClass as UserClassEnum;
use App\Models\User;

class BasePolicy
{
    /**
     * @param  string  $ability
     * @return void|bool
     */
    public function before(User $user, $ability)
    {
        if ($user->class >= UserClassEnum::STAFFLEADER->value) {
            return true;
        }
    }
}
