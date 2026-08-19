<?php

namespace App\Filament\Resources\System\ExamResource\Pages;

use Filament\Actions\DeleteAction;
use Exception;
use App\Filament\Resources\System\ExamResource;
use App\Models\Exam;
use App\Repositories\ExamRepository;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    private function getExamRecord(): Exam
    {
        $record = $this->record;
        if (! $record instanceof Exam) {
            throw new \RuntimeException('Expected an Exam record.');
        }
        return $record;
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $data = $this->form->getState();
        $examRep = new ExamRepository();
        try {
            $this->record = $examRep->update($data, $this->getExamRecord()->id);
            \App\Support\Admin::successNotification("");
            $this->redirect($this->getResource()::getUrl('index'));
        } catch (Exception $exception) {
            \App\Support\Admin::failNotification($exception->getMessage());
        }
    }
}
