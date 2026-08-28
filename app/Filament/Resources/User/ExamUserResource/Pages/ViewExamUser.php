<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\ExamUserResource\Pages;

use App\Filament\Resources\User\ExamUserResource;
use App\Models\ExamUser;
use App\Repositories\ExamRepository;
use App\Support\Admin;
use Carbon\Carbon;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewExamUser extends ViewRecord
{
    protected static string $resource = ExamUserResource::class;

    private function getExamUserRecord(): ExamUser
    {
        $record = $this->record;
        if (! $record instanceof ExamUser) {
            throw new \RuntimeException('Expected an ExamUser record.');
        }

        return $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDetailCardData(): array
    {
        $data = [];
        $record = $this->getExamUserRecord();
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
            'label' => __('label.exam.label'),
            'value' => $record->exam->name,
        ];
        $data[] = [
            'label' => __('label.begin'),
            'value' => $record->begin,
        ];
        $data[] = [
            'label' => __('label.end'),
            'value' => $record->end,
        ];
        $data[] = [
            'label' => __('label.exam_user.is_done'),
            'value' => $record->isDoneText,
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
        $exam = $this->getExamUserRecord()->exam;

        return [
            'cardData' => $this->getDetailCardData(),
            'result_pass_trans_key' => $exam->getPassResultTransKey('pass'),
            'result_not_pass_trans_key' => $exam->getPassResultTransKey('not_pass'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Avoid')
                ->requiresConfirmation()
                ->action(function () {
                    $examRep = app(ExamRepository::class);
                    try {
                        $examRep->avoidExamUser($this->getExamUserRecord()->id);
                        Admin::successNotification('');
                        $this->record = $this->resolveRecord($this->getExamUserRecord()->id);
                    } catch (Exception $exception) {
                        Admin::failNotification($exception->getMessage());
                    }
                })
                ->label(__('admin.resources.exam_user.action_avoid')),

            Action::make('UpdateEnd')
                ->mountUsing(fn (Schema $schema) => $schema->fill([
                    'end' => $this->getExamUserRecord()->end,
                ]))
                ->schema([
                    DateTimePicker::make('end')
                        ->required()
                        ->label(__('label.end')),
                    Textarea::make('reason')
                        ->label(__('label.reason')),
                ])
                ->action(function (array $data) {
                    $examRep = app(ExamRepository::class);
                    try {
                        $examRep->updateExamUserEnd($this->getExamUserRecord(), Carbon::parse($data['end']), $data['reason'] ?? '');
                        Admin::successNotification('');
                        $this->record = $this->resolveRecord($this->getExamUserRecord()->id);
                    } catch (Exception $exception) {
                        Admin::failNotification($exception->getMessage());
                    }
                })
                ->label(__('admin.resources.exam_user.action_update_end')),

            DeleteAction::make(),
        ];
    }
}
