<?php

declare(strict_types=1);

namespace App\Filament\Member\Pages;

use App\Models\Passkey;
use App\Support\Time;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class MyPasskeys extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.member.pages.my-passkeys';

    protected static ?string $slug = 'passkeys';

    protected Width|string|null $maxContentWidth = 'full';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    public function getTitle(): string|Htmlable
    {
        return __('passkey.passkey');
    }

    public static function getNavigationLabel(): string
    {
        return __('passkey.passkey');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Passkey::query()->where('user_id', Auth::id()))
            ->columns([
                TextColumn::make('credential_id')
                    ->label(__('passkey.fields.credential_id'))
                    ->limit(32)
                    ->tooltip(fn (Passkey $record) => $record->credential_id)
                    ->copyable(),
                TextColumn::make('aaguid')
                    ->label('AAGUID')
                    ->formatStateUsing(fn (Passkey $record) => $record->getAaguidFormatted())
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('label.created_at'))
                    ->formatStateUsing(fn ($state) => Time::formatDateTime($state)),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->label(__('passkey.passkey_delete'))
                    ->modalDescription(__('passkey.passkey_delete_confirm')),
            ])
            ->emptyStateHeading(__('passkey.passkey_empty'));
    }
}
