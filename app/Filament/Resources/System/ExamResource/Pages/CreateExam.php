<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\ExamResource\Pages;

use App\Filament\Resources\System\ExamResource;
use App\Repositories\ExamRepository;
use App\Support\Admin;
use App\Support\Logger;
use Exception;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    public function create(bool $another = false): void
    {
        $data = $this->form->getState();
        $examRep = app(ExamRepository::class);
        try {
            $this->record = $examRep->store($data);
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
            Logger::writeWithContext((string) ($exception->getMessage()."\n".$exception->getTraceAsString()), (string) 'error', (bool) false);
            Admin::failNotification($exception->getMessage());
        }
    }
}
