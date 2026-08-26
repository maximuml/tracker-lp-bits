<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\Globals;
use App\Support\Mail;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * @property Schema $delacctForm
 * @property Schema $massmailForm
 */
class SystemActions extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.system-actions';

    protected static string $routePath = 'system-actions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.system_actions');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->class >= User::CLASS_SYSOP;
    }

    /**
     * @var array<string, mixed>
     */
    public ?array $delacctData = [];

    /**
     * @var array<string, mixed>
     */
    public ?array $massmailData = [];

    public function mount(): void
    {
        $this->delacctForm->fill();
        $this->massmailForm->fill();
    }

    /**
     * @return array<int, string>
     */
    protected function getForms(): array
    {
        return [
            'delacctForm',
            'massmailForm',
        ];
    }

    public function delacctForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('admin.system_actions.delete_account'))
                    ->description(__('admin.system_actions.delete_account_desc'))
                    ->schema([
                        TextInput::make('userid')
                            ->label(__('admin.system_actions.user_id_or_username'))
                            ->required()
                            ->helperText(__('admin.system_actions.delete_account_help')),
                    ]),
            ])
            ->statePath('delacctData');
    }

    public function submitDelacct(): void
    {
        $data = $this->delacctForm->getState();
        $userid = trim((string) ($data['userid'] ?? ''));

        if ($userid === '') {
            Notification::make()->title('Error')->body('Please fill out the form correctly.')->danger()->send();

            return;
        }

        $user = User::query()->where('id', $userid)->first();
        if (! $user) {
            $user = User::query()->where('username', $userid)->first();
        }
        if (! $user) {
            Notification::make()->title('Error')->body('Bad user id or username.')->danger()->send();

            return;
        }

        $name = $user->username;
        $userRep = new UserRepository;
        $userRep->destroy((int) $user->id);

        Notification::make()
            ->title('Success')
            ->body("The account {$name} was deleted.")
            ->success()
            ->send();
        $this->delacctForm->fill();
    }

    public function massmailForm(Schema $schema): Schema
    {
        $classes = [];
        foreach (User::$classes as $classId => $info) {
            $classes[(int) $classId] = $info['text'] ?? "Class {$classId}";
        }

        return $schema
            ->schema([
                Section::make(__('admin.system_actions.mass_mail'))
                    ->description(__('admin.system_actions.mass_mail_desc'))
                    ->schema([
                        Select::make('class')
                            ->label(__('admin.system_actions.target_class'))
                            ->options($classes)
                            ->required(),
                        Select::make('or')
                            ->label(__('admin.system_actions.comparison'))
                            ->options([
                                '<' => '<',
                                '>' => '>',
                                '=' => '=',
                                '<=' => '<=',
                                '>=' => '>=',
                            ])
                            ->default('=')
                            ->required(),
                        TextInput::make('subject')
                            ->label(__('admin.system_actions.subject'))
                            ->maxLength(80),
                        Textarea::make('message')
                            ->label(__('admin.system_actions.message'))
                            ->rows(6)
                            ->required(),
                    ]),
            ])
            ->statePath('massmailData');
    }

    public function submitMassmail(): void
    {
        $data = $this->massmailForm->getState();
        $class = (int) ($data['class'] ?? 0);
        $or = (string) ($data['or'] ?? '=');
        $subject = trim((string) ($data['subject'] ?? ''));
        $messageBody = trim((string) ($data['message'] ?? ''));

        if (! in_array($or, ['<', '>', '=', '<=', '>='], true)) {
            Notification::make()->title('Error')->body('Invalid comparison operator.')->danger()->send();

            return;
        }
        if ($messageBody === '') {
            Notification::make()->title('Error')->body('Empty message!')->danger()->send();

            return;
        }

        $subject = $subject === '' ? '(no subject)' : $subject;
        $subject = "Fw: {$subject}";

        $users = User::query()->where('class', $or, $class)->get(['id', 'username', 'email']);
        if ($users->isEmpty()) {
            Notification::make()->title('Error')->body('No users match the selected criteria.')->warning()->send();

            return;
        }

        $siteName = (string) app(Globals::class)->get('SITENAME', '');
        $siteEmail = (string) app(Globals::class)->get('SITEEMAIL', '');
        $sent = false;
        foreach ($users as $userRow) {
            $to = (string) $userRow->email;
            $message = "Message received from {$siteName} on ".date('Y-m-d H:i:s').".\n".
                "---------------------------------------------------------------------\n\n".
                htmlspecialchars($messageBody)."\n\n".
                "---------------------------------------------------------------------\n{$siteName}\n";
            $sent = Mail::sentLegacy($to, $siteName, $siteEmail, $subject, $message, 'Mass Mail', false, false, '', 'UTF-8');
        }

        $sent
            ? Notification::make()->title('Success')->body("Messages sent to {$users->count()} users.")->success()->send()
            : Notification::make()->title('Error')->body('Try again.')->danger()->send();

        $this->massmailForm->fill();
    }
}
