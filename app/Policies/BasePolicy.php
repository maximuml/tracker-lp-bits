<?php

namespace App\Policies;

use App\Models\User;

class BasePolicy
{
    /**
     * @param  string  $ability
     * @return void|bool
     */
    public function before(User $user, $ability)
    {
        if ($user->class >= User::CLASS_STAFF_LEADER) {
            return true;
        }
    }
}
