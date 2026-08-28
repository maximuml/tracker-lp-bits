<?php

declare(strict_types=1);

namespace App\Filament\Resources\Security\StaffMessageResource\Pages;

use App\Filament\Resources\Security\StaffMessageResource;
use App\Models\Message;
use App\Models\StaffMessage;
use App\Models\User;
use App\Support\Cache;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewStaffMessage extends ViewRecord
{
    protected static string $resource = StaffMessageResource::class;

    private function getStaffMessageRecord(): StaffMessage
    {
        $record = $this->record;
        if (! $record instanceof StaffMessage) {
            throw new \RuntimeException('Expected a StaffMessage record.');
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        $record = $this->getStaffMessageRecord();
        $user = Auth::user();

        if ($record->answered == 0 && $user instanceof User) {
            $actions[] = Action::make('reply')
                ->label(__('label.staff_message.reply'))
                ->icon('heroicon-o-reply')
                ->schema([
                    TextInput::make('subject')->default('Re: '.$record->subject)->required(),
                    Textarea::make('body')->label(__('label.staff_message.reply_body'))->rows(4)->required(),
                ])
                ->action(function (array $data) use ($record, $user) {
                    Message::add([
                        'sender' => $user->id,
                        'receiver' => $record->sender,
                        'subject' => $data['subject'],
                        'msg' => $data['body'],
                        'added' => now(),
                    ]);
                    $record->update([
                        'answer' => $data['body'],
                        'answered' => 1,
                        'answeredby' => $user->id,
                    ]);
                    Cache::clearStaffMessage();
                });
        }

        return $actions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDetailCardData(): array
    {
        $data = [];
        $record = $this->getStaffMessageRecord();
        $data[] = ['label' => 'ID', 'value' => $record->id];
        $data[] = ['label' => __('label.staff_message.subject'), 'value' => $record->subject];
        $data[] = ['label' => __('label.staff_message.sender'), 'value' => $record->send_user->username ?? 'System'];
        $data[] = ['label' => __('label.staff_message.message'), 'value' => nl2br(e($record->msg))];
        $data[] = ['label' => __('label.staff_message.status'), 'value' => $record->answered ? __('label.staff_message.answered') : __('label.staff_message.unanswered')];
        $data[] = ['label' => __('label.staff_message.answered_by'), 'value' => $record->answer_user->username ?? 'N/A'];
        if ($record->answer) {
            $data[] = ['label' => __('label.staff_message.answer'), 'value' => nl2br(e($record->answer))];
        }
        $data[] = ['label' => __('label.added'), 'value' => $record->added];

        return $data;
    }

    protected function getViewData(): array
    {
        return [
            'cardData' => $this->getDetailCardData(),
        ];
    }
}
