<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\UserResource\Pages;

use App\Filament\Resources\User\UserResource;
use App\Repositories\UserRepository;
use App\Support\Admin;
use Exception;
use Filament\Actions\Contracts\HasActions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord implements HasActions
{
    protected static string $resource = UserResource::class;

    public function create(bool $another = false): void
    {
        $userRep = app(UserRepository::class);
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
