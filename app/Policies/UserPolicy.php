<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permission;
use App\Enums\UserClass as UserClassEnum;
use App\Models\User;
use App\Support\Logger;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy extends BasePolicy
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
    public function view(User $user, User $model)
    {
        return $model->privacy != 'strong' || $user->id == $model->id || Permission::canManageUserBasicInfo();
    }

    public function viewEmail(User $user, User $model): bool
    {
        Logger::writeWithContext((string) sprintf('user: %s, model: %s', $user->id, $model->id), (string) 'info', (bool) false);

        return $model->privacy == 'low' || $user->id == $model->id || Permission::canViewUserConfidentialInfo();
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
    public function update(User $user, User $model)
    {
        return $this->can($user);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return Response|bool
     */
    public function delete(User $user, User $model)
    {
        return $this->can($user);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return Response|bool
     */
    public function restore(User $user, User $model)
    {
        //

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, User $model)
    {
        //

        return false;
    }

    private function can(User $user): bool
    {
        if ($user->class >= UserClassEnum::ADMINISTRATOR->value) {
            return true;
        }

        return false;
    }
}
