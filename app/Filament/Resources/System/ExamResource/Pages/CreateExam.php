<?php

namespace App\Filament\Resources\System\ExamResource\Pages;

use Exception;
use App\Filament\Resources\System\ExamResource;
use App\Repositories\ExamRepository;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    public function create(bool $another = false): void
    {
        $data = $this->form->getState();
        $examRep = new ExamRepository();
        try {
            $this->record = $examRep->store($data);
            \App\Support\Admin::successNotification("");
            if ($another) {
                // Ensure that the form record is anonymized so that relationships aren't loaded.
                $this->form->model($this->record::class);
                $this->record = null;

                $this->fillForm();

                return;
            }
            $this->redirect($this->getResource()::getUrl('index'));
        } catch (Exception $exception) {
            \App\Support\Logger::writeWithContext((string) ($exception->getMessage() . "\n" . $exception->getTraceAsString()), (string) "error", (bool) false);
            \App\Support\Admin::failNotification($exception->getMessage());
        }
    }

}
