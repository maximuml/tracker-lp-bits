<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserClass as UserClassEnum;
use App\Models\User;
use App\Support\PhpErrorLogParser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class PhpErrorLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.php-error-log';

    protected Width|string|null $maxContentWidth = 'full';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 4;

    protected static string|null|UnitEnum $navigationGroup = 'System';

    public function getTitle(): string|Htmlable
    {
        return __('php-error-log.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('php-error-log.label');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->class >= UserClassEnum::SYSOP->value;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (array $filters, int $page, int $recordsPerPage): LengthAwarePaginator => self::getRecords($filters, $page, $recordsPerPage))
            ->columns([
                TextColumn::make('time')
                    ->label(__('php-error-log.time'))
                    ->width('11rem'),
                TextColumn::make('level')
                    ->label(__('php-error-log.level'))
                    ->badge()
                    ->color(fn (array $record): string => match ($record['level_key']) {
                        'fatal', 'exception' => 'danger',
                        'parse', 'compile' => 'danger',
                        'warning', 'user_error' => 'warning',
                        'notice', 'strict' => 'info',
                        'deprecated' => 'gray',
                        default => 'gray',
                    })
                    ->width('7rem'),
                TextColumn::make('message')
                    ->label(__('php-error-log.message'))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->label(__('php-error-log.level'))
                    ->options(PhpErrorLogParser::levelOptions()),
                Filter::make('time')
                    ->schema([
                        DatePicker::make('from')->label(__('php-error-log.from')),
                        DatePicker::make('until')->label(__('php-error-log.until')),
                    ]),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('php-error-log.view'))
                    ->modalHeading(__('php-error-log.entry_details'))
                    ->modalContent(fn (array $record) => view('filament.php-error-log-entry', ['full' => $record['full']]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('php-error-log.close')),
            ])
            ->emptyStateHeading(__('php-error-log.empty'))
            ->paginated([25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear')
                ->label(__('php-error-log.clear'))
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $path = self::logPath();
                    if (! is_file($path) || ! is_writable($path) || file_put_contents($path, '') === false) {
                        Notification::make()->title(__('php-error-log.clear_failed'))->danger()->send();

                        return;
                    }
                    Notification::make()->title(__('php-error-log.cleared'))->success()->send();
                    $this->resetTable();
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{id: int, time: string, level: string, level_key: string, message: string, context: string, full: string}>
     */
    private static function getRecords(array $filters, int $page, int $recordsPerPage): LengthAwarePaginator
    {
        $entries = PhpErrorLogParser::entries(self::logPath());

        $level = $filters['level']['value'] ?? null;
        if (is_string($level) && $level !== '') {
            $entries = array_values(array_filter($entries, fn (array $e): bool => $e['level_key'] === $level));
        }

        $fromTs = self::filterTimestamp($filters['time']['from'] ?? null, '00:00:00');
        $untilTs = self::filterTimestamp($filters['time']['until'] ?? null, '23:59:59');
        if ($fromTs !== null) {
            $entries = array_values(array_filter($entries, fn (array $e): bool => $e['time'] >= $fromTs || preg_match('/^\d{4}-\d{2}-\d{2}/', $e['time']) !== 1));
        }
        if ($untilTs !== null) {
            $entries = array_values(array_filter($entries, fn (array $e): bool => $e['time'] <= $untilTs || preg_match('/^\d{4}-\d{2}-\d{2}/', $e['time']) !== 1));
        }

        return new LengthAwarePaginator(
            array_slice($entries, ($page - 1) * $recordsPerPage, $recordsPerPage),
            total: count($entries),
            perPage: $recordsPerPage,
            currentPage: $page,
        );
    }

    private static function filterTimestamp(mixed $value, string $timeSuffix): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d').' '.$timeSuffix;
        }
        if (is_string($value) && $value !== '') {
            return (new \DateTimeImmutable($value))->format('Y-m-d').' '.$timeSuffix;
        }

        return null;
    }

    private static function logPath(): string
    {
        $path = (string) ini_get('error_log');
        if ($path !== '' && str_starts_with($path, '/')) {
            return $path;
        }

        return storage_path('logs/php_errors.log');
    }
}
