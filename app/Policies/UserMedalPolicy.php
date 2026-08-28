<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserClass as UserClassEnum;
use App\Models\User;
use App\Models\UserMedal;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserMedalPolicy extends BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return Response|bool
     */
    public function view(User $user, UserMedal $userMedal)
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @return Response|bool
     */
    public function create(User $user)
    {
        return $this->can($user);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return Response|bool
     */
    public function update(User $user, UserMedal $userMedal)
    {
        return $this->can($user);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return Response|bool
     */
    public function delete(User $user, UserMedal $userMedal)
    {
        return $this->can($user);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return Response|bool
     */
    public function restore(User $user, UserMedal $userMedal)
    {
        //

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, UserMedal $userMedal)
    {
        //

        return false;
    }

    private function can(User $user): bool
    {
        if ($user->class >= UserClassEnum::SYSOP->value) {
            return true;
        }

        return false;
    }
}
