<?php

namespace App\Filament\Resources\System\SeedBoxRecordResource\Pages;

use App\Filament\Resources\System\SeedBoxRecordResource;
use App\Models\SeedBoxRecord;
use App\Repositories\SeedBoxRepository;
use App\Support\Admin;
use Exception;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSeedBoxRecord extends EditRecord
{
    protected static string $resource = SeedBoxRecordResource::class;

    private function getSeedBoxRecord(): SeedBoxRecord
    {
        $record = $this->record;
        if (! $record instanceof SeedBoxRecord) {
            throw new \RuntimeException('Expected a SeedBoxRecord record.');
        }

        return $record;
    }

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $data = $this->form->getState();
        $rep = new SeedBoxRepository;
        try {
            $this->record = $rep->update($data, $this->getSeedBoxRecord()->id);
            Admin::successNotification('');
            $this->redirect($this->getResource()::getUrl('index'));
        } catch (Exception $exception) {
            Admin::failNotification($exception->getMessage());
        }
    }
}
