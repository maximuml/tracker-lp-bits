<?php

namespace App\Filament\Resources\User\HitAndRunResource\Pages;

use Filament\Actions\Action;
use Exception;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\User\HitAndRunResource;
use App\Models\HitAndRun;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewHitAndRun extends ViewRecord
{
    protected static string $resource = HitAndRunResource::class;

    private function getHitAndRunRecord(): HitAndRun
    {
        $record = $this->record;
        if (! $record instanceof HitAndRun) {
            throw new \RuntimeException('Expected a HitAndRun record.');
        }
        return $record;
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    private function getDetailCardData(): array
    {
        $data = [];
        $record = $this->getHitAndRunRecord();
        $data[] = [
            'label' => 'ID',
            'value' => $record->id,
        ];
        $data[] = [
            'label' => __('label.status'),
            'value' => $record->statusText,
        ];
        $data[] = [
            'label' => __('label.username'),
            'value' => $record->user->username,
        ];
        $data[] = [
            'label' => __('label.torrent.label'),
            'value' => $record->torrent->name,
        ];
        $snatch = $record->snatch;
        $data[] = [
            'label' => __('label.uploaded'),
            'value' => $snatch instanceof \App\Models\Snatch ? $snatch->uploadedText : '',
        ];
        $data[] = [
            'label' => __('label.downloaded'),
            'value' => $snatch instanceof \App\Models\Snatch ? $snatch->downloadedText : '',
        ];
        $data[] = [
            'label' => __('label.ratio'),
            'value' => $snatch instanceof \App\Models\Snatch ? $snatch->shareRatio : '',
        ];
        $data[] = [
            'label' => __('label.seed_time_required'),
            'value' => $record->seedTimeRequired,
        ];
        $data[] = [
            'label' => __('label.inspect_time_left'),
            'value' => $record->inspectTimeLeft,
        ];
        $data[] = [
            'label' => __('label.comment'),
            'value' => nl2br($record->comment),
        ];
        $data[] = [
            'label' => __('label.created_at'),
            'value' => $record->created_at,
        ];
        $data[] = [
            'label' => __('label.updated_at'),
            'value' => $record->updated_at,
        ];
        return $data;
    }

    protected function getViewData(): array
    {
        return [
            'cardData' => $this->getDetailCardData(),
        ];
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        $record = $this->getHitAndRunRecord();
        if (in_array($record->status, HitAndRun::CAN_PARDON_STATUS)) {
            $actions[] = Action::make('Pardon')
                ->requiresConfirmation()
                ->action(function () {
                    $hitAndRunRep = new HitAndRunRepository();
                    $user = Auth::user();
                    if (! $user instanceof User) {
                        throw new \RuntimeException('Expected an authenticated user.');
                    }
                    try {
                        $hitAndRunRep->pardon($this->getHitAndRunRecord()->id, $user);
                        \App\Support\Admin::successNotification("");
                        $this->record = $this->resolveRecord($this->getHitAndRunRecord()->id);
                    } catch (Exception $exception) {
                        \App\Support\Admin::failNotification($exception->getMessage());
                    }
                })
                ->label(__('admin.resources.hit_and_run.action_pardon'))
            ;
        }
        $actions[] = DeleteAction::make();

        return $actions;
    }

}
