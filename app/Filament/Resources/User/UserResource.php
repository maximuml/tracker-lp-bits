<?php

declare(strict_types=1);

namespace App\Filament\Resources\User;

use App\Enums\ModelEventEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Enums\UserStatus;
use App\Filament\OptionsTrait;
use App\Filament\Resources\User\UserResource\Pages;
use App\Filament\Resources\User\UserResource\Pages\CreateUser;
use App\Filament\Resources\User\UserResource\Pages\ListUsers;
use App\Filament\Resources\User\UserResource\Pages\UserProfile;
use App\Filament\Resources\User\UserResource\RelationManagers;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\Admin;
use App\Support\Config\SiteConfig;
use App\Support\Events;
use App\Support\Mail;
use App\Support\Url;
use App\Support\UserDisplay;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class UserResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'User';

    protected static ?int $navigationSort = 1;

    private static ?UserRepository $rep = null;

    private static function currentUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Expected an authenticated user.');
        }

        return $user;
    }

    private static function getRep(): UserRepository
    {
        if (self::$rep === null) {
            self::$rep = app(UserRepository::class);
        }

        return self::$rep;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.users_list');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')->required(),
                TextInput::make('email')->required(),
                TextInput::make('password')->password()->required()->visibleOn(CreateUser::class),
                TextInput::make('password_confirmation')->password()->required()->same('password')->visibleOn(CreateUser::class),
                TextInput::make('id')->integer(),
                Select::make('class')->options(User::listClass(UserClassEnum::PEASANT->value, self::currentUser()->class - 1)),
            ]);
    }

    public static function table(Table $table): Table
    {
        $yesNoOptions = ['success' => 'yes', 'danger' => 'no'];

        return $table
            ->columns([
                TextColumn::make('id')->sortable()->searchable(),
                TextColumn::make('username')->searchable()->label(__('label.user.username'))
                    ->formatStateUsing(fn ($record) => new HtmlString(UserDisplay::username($record->id, false, true, true, true))),
                TextColumn::make('email')->searchable()->label(__('label.email')),
                TextColumn::make('class')->label('Class')
                    ->formatStateUsing(fn (Column $column) => ($record = $column->getRecord()) instanceof User ? $record->classText : '')
                    ->sortable()->label(__('label.user.class')),
                TextColumn::make('uploaded')->label('Uploaded')
                    ->formatStateUsing(fn (Column $column) => ($record = $column->getRecord()) instanceof User ? $record->uploadedText : '')
                    ->sortable()->label(__('label.uploaded')),
                TextColumn::make('downloaded')->label('Downloaded')
                    ->formatStateUsing(fn (Column $column) => ($record = $column->getRecord()) instanceof User ? $record->downloadedText : '')
                    ->sortable()->label(__('label.downloaded')),
                TextColumn::make('status')->badge()->colors(['success' => 'confirmed', 'warning' => 'pending'])->label(__('label.user.status')),
                TextColumn::make('enabled')->badge()->colors($yesNoOptions)->label(__('label.user.enabled')),
                TextColumn::make('downloadpos')->badge()->colors($yesNoOptions)->label(__('label.user.downloadpos')),
                TextColumn::make('parked')->badge()->colors($yesNoOptions)->label(__('label.user.parked')),
                TextColumn::make('warned')->badge()->colors($yesNoOptions)->label(__('label.user.warned')),
                TextColumn::make('isDonating')
                    ->state(fn ($record): string => $record->isDonating() ? 'yes' : 'no')
                    ->badge()
                    ->colors($yesNoOptions)
                    ->label(__('label.user.is_donating')),
                TextColumn::make('added')->sortable()->dateTime('Y-m-d H:i')->label(__('label.added')),
                TextColumn::make('last_access')->dateTime('Y-m-d H:i')->label(__('label.last_access')),
            ])
            ->defaultSort('added', 'desc')
            ->filters([
                Filter::make('id')
                    ->schema([
                        TextInput::make('id')
                            ->placeholder('UID'),
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['id'], fn (Builder $query, $id) => $query->where('id', $id));
                    }),
                SelectFilter::make('class')->options(User::listClass())->label(__('label.user.class')),
                SelectFilter::make('status')->options(['confirmed' => 'confirmed', 'pending' => 'pending'])->label(__('label.user.status')),
                SelectFilter::make('enabled')->options(self::$yesOrNo)->label(__('label.user.enabled')),
                SelectFilter::make('downloadpos')->options(self::$yesOrNo)->label(__('label.user.downloadpos')),
                SelectFilter::make('parked')->options(self::$yesOrNo)->label(__('label.user.parked')),
                SelectFilter::make('warned')->options(self::$yesOrNo)->label(__('label.user.warned'))
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'yes') {
                            return $query->where('warned', 'yes');
                        } elseif ($data['value'] === 'no') {
                            return $query->where('warned', 'no');
                        }

                        return $query;
                    }),
                SelectFilter::make('is_donating')
                    ->options(self::$yesOrNo)
                    ->label(__('label.user.is_donating'))
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'yes') {
                            return $query->where('donor', 'yes')->where(function ($query) {
                                return $query->whereNull('donoruntil')->orWhere('donoruntil', '>=', now());
                            });
                        } elseif ($data['value'] === 'no') {
                            return $query->where('donor', 'no');
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions(self::getBulkActions());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Group::make([
                        TextEntry::make('id')->label('UID'),
                        TextEntry::make('username')
                            ->label(__('label.user.username'))
                            ->formatStateUsing(fn ($record) => UserDisplay::username($record->id, false, true, true, true))
                            ->html(),
                        TextEntry::make('email')
                            ->label(__('label.email'))
                            ->copyable()
                            ->placeholder('点击复制'),
                        TextEntry::make('passkey')->limit(10)->copyable(),
                        TextEntry::make('genderText')
                            ->label(__('label.user.gender'))
                            ->placeholder('N/A'),
                        TextEntry::make('country.name')
                            ->label(__('label.user.country'))
                            ->placeholder('N/A'),
                        TextEntry::make('added')->label(__('label.added')),
                        TextEntry::make('last_access')->label(__('label.last_access')),
                        TextEntry::make('inviter.username')->label(__('label.user.invite_by')),
                        TextEntry::make('ip')
                            ->label(__('label.user.ip'))
                            ->visible(fn (User $record): bool => self::currentUser()->class >= UserClassEnum::MODERATOR->value)
                            ->placeholder('N/A'),
                        TextEntry::make('parked')->label(__('label.user.parked')),
                        TextEntry::make('offer_allowed_count')->label(__('label.user.offer_allowed_count')),
                        TextEntry::make('seed_points')->label(__('label.user.seed_points')),
                        TextEntry::make('uploadedText')->label(__('label.uploaded')),
                        TextEntry::make('downloadedText')->label(__('label.downloaded')),
                        TextEntry::make('seedbonus')->label(__('label.user.seedbonus')),
                        TextEntry::make('seed_points')->label(__('label.user.seed_points')),
                    ])
                        ->columns(6)
                        ->columnSpan(4),

                    Group::make([
                        TextEntry::make('status')
                            ->label(__('label.user.status'))
                            ->badge()
                            ->colors(['success' => UserStatus::CONFIRMED->value, 'warning' => UserStatus::PENDING->value])
                            ->hintAction(self::buildActionConfirm()),

                        TextEntry::make('classText')
                            ->label(__('label.user.class'))
                            ->hintAction(self::buildActionChangeClass()),

                        TextEntry::make('enabled')
                            ->label(__('label.user.enabled'))
                            ->badge()
                            ->colors(['success' => 'yes', 'warning' => 'no'])
                            ->hintAction(self::buildActionEnableDisable()),
                        TextEntry::make('downloadpos')
                            ->label(__('label.user.downloadpos'))
                            ->badge()
                            ->colors(['success' => 'yes', 'warning' => 'no'])
                            ->hintAction(self::buildActionChangeDownloadPos()),
                        TextEntry::make('twoFactorAuthenticationStatus')
                            ->label(__('label.user.two_step_authentication'))
                            ->badge()
                            ->colors(['success' => 'yes', 'warning' => 'no'])
                            ->hintAction(self::buildActionCancelTwoStepAuthentication()),
                    ])
                        ->columnSpan(1),
                ])->columns(5),
            ]);
    }

    private static function buildActionChangeClass(): Action
    {
        return Action::make('changeClass')
            ->label(__('label.change'))
            ->button()
            ->visible(fn (User $record): bool => (self::currentUser()->class > $record->class))
            ->schema([
                Select::make('class')
                    ->options(User::listClass(UserClassEnum::PEASANT->value, self::currentUser()->class - 1))
                    ->default(fn (User $record) => $record->class)
                    ->label(__('user.labels.class'))
                    ->required()
                    ->reactive(),
                Radio::make('vip_added')
                    ->options(self::getYesNoOptions('yes', 'no'))
                    ->default(fn (User $record) => $record->vip_added)
                    ->label(__('user.labels.vip_added'))
                    ->helperText(__('user.labels.vip_added_help'))
                    ->hidden(fn (Get $get) => $get('class') != UserClassEnum::VIP->value),
                DateTimePicker::make('vip_until')
                    ->default(fn (User $record) => $record->vip_until)
                    ->label(__('user.labels.vip_until'))
                    ->helperText(__('user.labels.vip_until_help'))
                    ->hidden(fn (Get $get) => $get('class') != UserClassEnum::VIP->value),
                TextInput::make('reason')
                    ->label(__('admin.resources.user.actions.enable_disable_reason'))
                    ->placeholder(__('admin.resources.user.actions.enable_disable_reason_placeholder')),
            ])
            ->action(function (User $record, array $data) {
                $userRep = self::getRep();
                try {
                    $userRep->changeClass(self::currentUser(), $record, $data['class'], $data['reason'], $data);
                    Admin::successNotification('');
                } catch (Exception $exception) {
                    Admin::failNotification($exception->getMessage());
                }
            });
    }

    private static function buildActionConfirm(): Action
    {
        return Action::make(__('admin.resources.user.actions.confirm_btn'))
            ->modalHeading(__('admin.resources.user.actions.confirm_btn'))
            ->visible(fn (User $record): bool => (self::currentUser()->class > $record->class))
            ->button()
            ->color('success')
            ->visible(fn ($record) => $record->status == UserStatus::PENDING->value)
            ->schema([
                Forms\Components\Checkbox::make('send_email')
                    ->label(__('admin.resources.user.actions.confirm_send_email'))
                    ->helperText(__('admin.resources.user.actions.confirm_send_email_help'))
                    ->default(true),
            ])
            ->action(function (User $record, array $data) {
                if (self::currentUser()->class <= $record->class) {
                    Admin::failNotification('No Permission!');

                    return;
                }
                $record->status = UserStatus::CONFIRMED->value;
                $record->info = null;
                $record->save();

                Events::fire(ModelEventEnum::USER_UPDATED, $record, null);

                if (! empty($data['send_email']) && $record->email !== '') {
                    self::sendConfirmationEmail($record);
                }

                Admin::successNotification('');
            });
    }

    /**
     * Send the confirmation email to a newly-confirmed user.
     *
     * Mirrors the legacy takeconfirm email flow.
     */
    private static function sendConfirmationEmail(User $user): void
    {
        $siteName = SiteConfig::current()->basic->siteName('');
        $baseUrl = Url::schemeAndHost(false);
        $reportMail = (string) SiteConfig::current()->main->siteEmail('');
        $siteEmail = (string) SiteConfig::current()->main->siteEmail('');

        $body = sprintf(
            "Your account has been confirmed.\n\n<b><a href=\"javascript:void(null)\" onclick=\"window.open('%s/login')\">Click here to login</a></b><br />\n%s/login\n\nIf you have any questions, please contact %s",
            $baseUrl,
            $baseUrl,
            $reportMail,
        );

        $subject = $siteName.' - Account Confirmed';

        Mail::sentLegacy(
            (string) $user->email,
            $siteName,
            $siteEmail,
            $subject,
            $body,
            'invite confirm',
            false,
            false,
            '',
            'UTF-8',
        );
    }

    private static function buildActionEnableDisable(): Action
    {
        return Action::make('changeClass')
            ->label(fn (User $record) => $record->enabled == 'yes' ? __('admin.resources.user.actions.disable_modal_btn') : __('admin.resources.user.actions.enable_modal_btn'))
            ->modalHeading(fn (User $record) => $record->enabled == 'yes' ? __('admin.resources.user.actions.disable_modal_title') : __('admin.resources.user.actions.enable_modal_title'))
            ->button()
            ->visible(fn (User $record): bool => (self::currentUser()->class > $record->class))
            ->schema([
                TextInput::make('reason')->label(__('admin.resources.user.actions.enable_disable_reason'))->placeholder(__('admin.resources.user.actions.enable_disable_reason_placeholder')),
                Hidden::make('action')->default(fn (User $record) => $record->enabled == 'yes' ? 'disable' : 'enable'),
                Hidden::make('uid')->default(fn (User $record) => $record->id),
            ])
            ->action(function (User $record, array $data) {
                $userRep = self::getRep();
                try {
                    if ($data['action'] == 'enable') {
                        $userRep->enableUser(self::currentUser(), $data['uid'], $data['reason']);
                    } elseif ($data['action'] == 'disable') {
                        $userRep->disableUser(self::currentUser(), $data['uid'], $data['reason']);
                    }
                    Admin::successNotification('');
                } catch (Exception $exception) {
                    Admin::failNotification($exception->getMessage());
                }
            });
    }

    private static function buildActionChangeDownloadPos(): Action
    {
        return Action::make('changeDownloadPos')
            ->label(fn (User $record) => $record->downloadpos == 'yes' ? __('admin.resources.user.actions.disable_download_privileges_btn') : __('admin.resources.user.actions.enable_download_privileges_btn'))
            ->button()
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => (self::currentUser()->class > $record->class))
            ->action(function (User $record) {
                $userRep = self::getRep();
                try {
                    $userRep->updateDownloadPrivileges(self::currentUser(), $record->id, $record->downloadpos == 'yes' ? 'no' : 'yes');
                    Admin::successNotification('');
                } catch (Exception $exception) {
                    Admin::failNotification($exception->getMessage());
                }
            });

    }

    private static function buildActionCancelTwoStepAuthentication(): Action
    {
        return Action::make('twoStepAuthentication')
            ->label(__('admin.resources.user.actions.disable_two_step_authentication'))
            ->button()
            ->visible(fn (User $record) => $record->two_step_secret != '')
            ->modalHeading(__('admin.resources.user.actions.disable_two_step_authentication'))
            ->requiresConfirmation()
            ->action(function (User $record) {
                $userRep = self::getRep();
                try {
                    $userRep->removeTwoStepAuthentication(self::currentUser(), $record->id);
                    Admin::successNotification('');
                } catch (Exception $exception) {
                    Admin::failNotification($exception->getMessage());
                }
            });

    }

    public static function getRelations(): array
    {
        return [
            //            RelationManagers\MedalsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            //            'edit' => Pages\EditUser::route('/{record}/edit'),
            //            'view' => Pages\ViewUser::route('/{record}'),
            'view' => UserProfile::route('/{record}'),
        ];
    }

    /** @return array<BulkAction> */
    public static function getBulkActions(): array
    {
        $actions = [];
        $currentUser = self::currentUser();

        if ($currentUser->class >= UserClassEnum::SYSOP->value) {
            $actions[] = BulkAction::make('confirm')
                ->label(__('admin.resources.user.actions.confirm_bulk'))
                ->requiresConfirmation()
                ->deselectRecordsAfterCompletion()
                ->action(function (Collection $records) {
                    $rep = self::getRep();
                    $rep->confirmUser($records->pluck('id')->toArray());
                });
        }

        if ($currentUser->class >= UserClassEnum::MODERATOR->value) {
            $actions[] = BulkAction::make('remove_warning')
                ->label(__('admin.resources.user.actions.remove_warning_bulk'))
                ->requiresConfirmation()
                ->deselectRecordsAfterCompletion()
                ->action(function (Collection $records) {
                    $rep = self::getRep();
                    $rep->removeWarnings(self::currentUser(), $records->pluck('id')->toArray());
                    Admin::successNotification('');
                });

            $actions[] = BulkAction::make('disable')
                ->label(__('admin.resources.user.actions.disable_bulk'))
                ->requiresConfirmation()
                ->deselectRecordsAfterCompletion()
                ->action(function (Collection $records) {
                    $rep = self::getRep();
                    foreach ($records as $record) {
                        if ($record instanceof User && $record->enabled === 'yes') {
                            try {
                                $rep->disableUser(self::currentUser(), $record->id, __('admin.resources.user.actions.disable_bulk_reason'));
                            } catch (Exception $e) {
                                // continue with other users
                            }
                        }
                    }
                    Admin::successNotification('');
                });
        }

        return $actions;
    }
}
