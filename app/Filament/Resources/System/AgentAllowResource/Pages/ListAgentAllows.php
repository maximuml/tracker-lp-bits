<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\AgentAllowResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\AgentAllowResource;
use App\Repositories\AgentAllowRepository;
use App\Support\Admin;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;

class ListAgentAllows extends PageList
{
    protected static string $resource = AgentAllowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('check')
                ->label(__('admin.resources.agent_allow.check_modal_btn'))
                ->schema([
                    TextInput::make('peer_id')->required(),
                    TextInput::make('agent')->required(),
                ])
                ->modalHeading(__('admin.resources.agent_allow.check_modal_header'))
                ->action(function ($data) {
                    $agentAllowRep = app(AgentAllowRepository::class);
                    try {
                        $result = $agentAllowRep->checkClient($data['peer_id'], $data['agent']);
                        Admin::successNotification(__('admin.resources.agent_allow.check_pass_msg', ['id' => $result->id]));
                    } catch (Exception $exception) {
                        Admin::failNotification($exception->getMessage());
                    }
                }),

        ];
    }
}
