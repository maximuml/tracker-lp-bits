<?php

namespace App\Filament\Resources\System\SeedBoxRecordResource\Pages;

use App\Filament\Resources\System\SeedBoxRecordResource;
use App\Models\SeedBoxRecord;
use App\Repositories\SeedBoxRepository;
use App\Support\Admin;
use Exception;
use Filament\Resources\Pages\CreateRecord;

class CreateSeedBoxRecord extends CreateRecord
{
    protected static string $resource = SeedBoxRecordResource::class;

    public function create(bool $another = false): void
    {
        $data = $this->form->getState();
        $data['uid'] = auth()->id();
        $data['type'] = SeedBoxRecord::TYPE_ADMIN;
        $data['status'] = SeedBoxRecord::STATUS_ALLOWED;
        $rep = new SeedBoxRepository;
        try {
            $this->record = $rep->store($data);
            Admin::successNotification('');
            if ($another) {
                // Ensure that the form record is anonymized so that relationships aren't loaded.
                $this->form->model($this->record::class);
                $this->record = null;

                $this->fillForm();

                return;
            }
            $this->redirect($this->getResource()::getUrl('index'));
        } catch (Exception $exception) {
            Admin::failNotification($exception->getMessage());
        }
    }
}
