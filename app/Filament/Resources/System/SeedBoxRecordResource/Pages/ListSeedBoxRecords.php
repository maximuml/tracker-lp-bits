<?php

namespace App\Filament\Resources\System\SeedBoxRecordResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\SeedBoxRecordResource;
use App\Repositories\SeedBoxRepository;
use App\Support\Locale;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;

class ListSeedBoxRecords extends PageList
{
    protected static string $resource = SeedBoxRecordResource::class;

    /** @var array<int|string, mixed>|null */
    protected static ?array $checkResult = null;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('check')
                ->label(__('admin.resources.seed_box_record.check_modal_btn'))
                ->schema([
                    TextInput::make('ip')->required()->label('IP'),
                    TextInput::make('uid')->required()->label('UID'),
                ])
                ->modalHeading(__('admin.resources.seed_box_record.check_modal_header'))
                ->action(function (array $data) {
                    $result = SeedBoxRepository::isSeedBoxFromUserRecords($data['uid'], $data['ip']);
                    self::$checkResult = $result;
                })
                ->registerModalActions([
                    Action::make('checkResult')
                        ->modalHeading(function () {
                            if (self::$checkResult !== null) {
                                if (self::$checkResult['result']) {
                                    return Locale::trans('seed-box.is_seed_box_yes', [], null);
                                } else {
                                    return Locale::trans('seed-box.is_seed_box_no', [], null);
                                }
                            }

                            return 'Unknown';
                        })
                        ->action(null)
                        ->modalSubmitAction(false)
                        ->modalCancelAction(false)
                        ->modalDescription(fn () => new HtmlString(self::$checkResult['desc'] ?? '')),
                ])
                ->after(function () {
                    $this->mountAction('checkResult');
                }),
        ];
    }
}
