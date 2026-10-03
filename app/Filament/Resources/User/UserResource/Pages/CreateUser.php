<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\UserResource\Pages;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Filament\Resources\User\UserResource;
use App\Support\Admin;
use Exception;
use Filament\Actions\Contracts\HasActions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord implements HasActions
{
    protected static string $resource = UserResource::class;

    private UserRepositoryInterface $userRepository;

    public function boot(
        UserRepositoryInterface $userRepository,
    ): void {
        $this->userRepository = $userRepository;
    }

    public function create(bool $another = false): void
    {
        $userRep = $this->userRepository;
        $data = $this->form->getState();
        try {
            $this->record = $userRep->store($data);
            Admin::successNotification('');
            $this->redirect($this->getRedirectUrl());
        } catch (Exception $exception) {
            Admin::failNotification($exception->getMessage());
        }
    }
}
