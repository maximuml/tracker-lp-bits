<?php

namespace App\Filament\Resources\User\UserResource\Pages;

use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Exception;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Actions\DeleteAction;
use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Filament\OptionsTrait;
use App\Filament\Resources\User\UserResource;
use App\Models\Exam;
use App\Models\Invite;
use App\Models\Medal;
use App\Models\User;
use App\Models\UserMeta;
use App\Repositories\ExamRepository;
use App\Repositories\MedalRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Nexus\Database\NexusDB;

class UserProfile extends ViewRecord implements HasActions
{
    use InteractsWithRecord;
    use HasRelationManagers;
    use OptionsTrait;

    private static ?UserRepository $rep = null;

    protected static string $resource = UserResource::class;

//    protected static string $view = 'filament.resources.user.user-resource.pages.user-profile';

    private function getRep(): UserRepository
    {
        if (!self::$rep) {
            self::$rep = new UserRepository();
        }
        return self::$rep;
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Expected an authenticated user.');
        }
        return $user;
    }

    private function getUserRecord(): User
    {
        $record = $this->record;
        if (! $record instanceof User) {
            throw new \RuntimeException('Expected a user record.');
        }
        return $record;
    }


    protected function getHeaderActions(): array
    {
        $actions = [];
        $user = $this->currentUser();
        $record = $this->getUserRecord();
        if ($user->class > $record->class) {
            if ($record->status == User::STATUS_PENDING) {
                $actions[] = $this->buildConfirmAction();
            }
            $actions[] = $this->buildWarnAction();
            $actions[] = $this->buildGrantPropsAction();
            $actions[] = $this->buildGrantMedalAction();
            $actions[] = $this->buildAssignExamAction();
            $actions[] = $this->buildChangeBonusEtcAction();
//            if ($this->getUserRecord()->two_step_secret) {
//                $actions[] = $this->buildDisableTwoStepAuthenticationAction();
//            }
            $actions[] = $this->buildResetPasswordAction();
            $actions[] = $this->buildEnableDisableAction();
            $actions[] = $this->buildEnableDisableDownloadPrivilegesAction();
            $actions[] = $this->buildEnableDisableUploadPrivilegesAction();
            $actions[] = $this->buildEnableDisableForumPostAction();
//            if (user_can('user-change-class')) {
//                $actions[] = $this->buildChangeClassAction();
//            }
            if (Permission::can(PermissionEnum::USER_DELETE)) {
                $actions[] = $this->buildDeleteAction();
            }
            $actions = \App\Support\Hooks::applyFilter('user_profile_actions', $actions);
        }
        return $actions;
    }

    protected function buildEnableDisableAction(): Action
    {
        return Action::make('enable_disable')
            ->label($this->getUserRecord()->enabled == 'yes' ? __('admin.resources.user.actions.disable_modal_btn') : __('admin.resources.user.actions.enable_modal_btn'))
            ->modalHeading($this->getUserRecord()->enabled == 'yes' ? __('admin.resources.user.actions.disable_modal_title') : __('admin.resources.user.actions.enable_modal_title'))
            ->schema([
                TextInput::make('reason')->label(__('admin.resources.user.actions.enable_disable_reason'))->placeholder(__('admin.resources.user.actions.enable_disable_reason_placeholder')),
                Hidden::make('action')->default($this->getUserRecord()->enabled == 'yes' ? 'disable' : 'enable'),
                Hidden::make('uid')->default($this->getUserRecord()->id),
            ])
//            ->visible(false)
//            ->hidden(true)
            ->action(function ($data) {
                $userRep = $this->getRep();
                try {
                    if ($data['action'] == 'enable') {
                        $userRep->enableUser($this->currentUser(), $data['uid'], $data['reason']);
                    } elseif ($data['action'] == 'disable') {
                        $userRep->disableUser($this->currentUser(), $data['uid'], $data['reason']);
                    }
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    protected function buildDisableTwoStepAuthenticationAction(): Action
    {
        return Action::make(__('admin.resources.user.actions.disable_two_step_authentication'))
            ->modalHeading(__('admin.resources.user.actions.disable_two_step_authentication'))
            ->requiresConfirmation()
            ->action(function ($data) {
                $userRep = $this->getRep();
                try {
                    $userRep->removeTwoStepAuthentication($this->currentUser(), $this->getUserRecord()->id);
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildChangeBonusEtcAction(): Action
    {
        return Action::make(__('admin.resources.user.actions.change_bonus_etc_btn'))
            ->modalHeading(__('admin.resources.user.actions.change_bonus_etc_btn'))
            ->schema([
                Radio::make('field')->options([
                    'uploaded' => __('label.user.uploaded'),
                    'downloaded' => __('label.user.downloaded'),
                    'invites' => __('label.user.invites'),
                    'seedbonus' => __('label.user.seedbonus'),
                    'attendance_card' => __('label.user.attendance_card'),
                    'tmp_invites' => __('label.user.tmp_invites'),
                ])
                    ->label(__('admin.resources.user.actions.change_bonus_etc_field_label'))
                    ->inline()
                    ->required()
                    ->reactive()
                ,
                Radio::make('action')->options([
                    'Increment' => __("admin.resources.user.actions.change_bonus_etc_action_increment"),
                    'Decrement' => __("admin.resources.user.actions.change_bonus_etc_action_decrement"),
                ])
                    ->label(__('admin.resources.user.actions.change_bonus_etc_action_label'))
                    ->inline()
                    ->required()
                ,
                TextInput::make('value')->integer()->required()
                    ->label(__('admin.resources.user.actions.change_bonus_etc_value_label'))
                    ->helperText(__('admin.resources.user.actions.change_bonus_etc_value_help'))
                ,

                TextInput::make('duration')->integer()
                    ->label(__('admin.resources.user.actions.change_bonus_etc_duration_label'))
                    ->helperText(__('admin.resources.user.actions.change_bonus_etc_duration_help'))
                    ->hidden(fn (Get $get) => $get('field') != 'tmp_invites')
                ,

                TextInput::make('reason')
                    ->label(__('admin.resources.user.actions.change_bonus_etc_reason_label'))
                ,
            ])
            ->action(function ($data) {
                $userRep = $this->getRep();
                try {
                    if ($data['field'] == 'tmp_invites') {
                        $userRep->addTemporaryInvite($this->currentUser(), $this->getUserRecord()->id, $data['action'], $data['value'], $data['duration'], $data['reason']);
                    } else {
                        $userRep->incrementDecrement($this->currentUser(), $this->getUserRecord()->id, $data['action'], $data['field'], $data['value'], $data['reason']);
                    }
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildResetPasswordAction(): Action
    {
        return Action::make(__('admin.resources.user.actions.reset_password_btn'))
            ->modalHeading(__('admin.resources.user.actions.reset_password_btn'))
            ->schema([
                TextInput::make('password')->label(__('admin.resources.user.actions.reset_password_label'))->required(),
                TextInput::make('password_confirmation')
                    ->label(__('admin.resources.user.actions.reset_password_confirmation_label'))
                    ->same('password')
                    ->required(),
            ])
            ->action(function ($data) {
                $userRep = $this->getRep();
                try {
                    $userRep->resetPassword($this->getUserRecord()->id, $data['password'], $data['password_confirmation']);
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildAssignExamAction(): Action
    {
        return Action::make(__('admin.resources.user.actions.assign_exam_btn'))
            ->modalHeading(__('admin.resources.user.actions.assign_exam_btn'))
            ->schema([
                Select::make('exam_id')
                    ->options((new ExamRepository())->listMatchExam($this->getUserRecord()->id)->pluck('name', 'id'))
                    ->label(__('admin.resources.user.actions.assign_exam_exam_label'))->required(),
                DateTimePicker::make('begin')->label(__('admin.resources.user.actions.assign_exam_begin_label')),
                DateTimePicker::make('end')->label(__('admin.resources.user.actions.assign_exam_end_label'))
                    ->helperText(__('admin.resources.user.actions.assign_exam_end_help')),

            ])
            ->action(function ($data) {
                $examRep = new ExamRepository();
                try {
                    $examRep->assignToUser($this->getUserRecord()->id, $data['exam_id'], $data['begin'], $data['end']);
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildGrantMedalAction(): Action
    {
        return Action::make(__('admin.resources.user.actions.grant_medal_btn'))
            ->modalHeading(__('admin.resources.user.actions.grant_medal_btn'))
            ->schema([
                Select::make('medal_id')
                    ->options(Medal::query()->pluck('name', 'id'))
                    ->label(__('admin.resources.user.actions.grant_medal_medal_label'))
                    ->required(),

                TextInput::make('duration')
                    ->label(__('admin.resources.user.actions.grant_medal_duration_label'))
                    ->helperText(__('admin.resources.user.actions.grant_medal_duration_help'))
                    ->integer(),

            ])
            ->action(function ($data) {
                $medalRep = new MedalRepository();
                try {
                    $medalRep->grantToUser($this->getUserRecord()->id, $data['medal_id'], $data['duration']);
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    protected function buildConfirmAction(): Action
    {
        return Action::make(__('admin.resources.user.actions.confirm_btn'))
            ->modalHeading(__('admin.resources.user.actions.confirm_btn'))
            ->color('success')
            ->schema([
                Forms\Components\Checkbox::make('send_email')
                    ->label(__('admin.resources.user.actions.confirm_send_email'))
                    ->helperText(__('admin.resources.user.actions.confirm_send_email_help'))
                    ->default(true),
            ])
            ->action(function (array $data) {
                if ($this->currentUser()->class <= $this->getUserRecord()->class) {
                    \App\Support\Admin::failNotification("No permission!");
                    return;
                }
                $record = $this->getUserRecord();
                $record->status = User::STATUS_CONFIRMED;
                $record->info = null;
                $record->save();

                \App\Support\Events::fire(\App\Enums\ModelEventEnum::USER_UPDATED, $record, null);

                if (!empty($data['send_email']) && $record->email !== '') {
                    $siteName = \App\Support\Config\SiteConfig::current()->basic->siteName('');
                    $baseUrl = \App\Support\Url::schemeAndHost(false);
                    $siteEmail = (string) \App\Support\Config\SiteConfig::current()->main->siteEmail('');

                    $body = sprintf(
                        "Your account has been confirmed.\n\n<b><a href=\"javascript:void(null)\" onclick=\"window.open('%s/login')\">Click here to login</a></b><br />\n%s/login",
                        $baseUrl,
                        $baseUrl,
                    );

                    \App\Support\Mail::sentLegacy(
                        $record->email,
                        $siteName,
                        $siteEmail,
                        $siteName . ' - Account Confirmed',
                        $body,
                        'invite confirm',
                        false,
                        false,
                        '',
                        'UTF-8',
                    );
                }
                $this->sendSuccessNotification();
            });
    }


    protected function buildEnableDisableDownloadPrivilegesAction(): Action
    {
        return Action::make($this->getUserRecord()->downloadpos == 'yes' ? __('admin.resources.user.actions.disable_download_privileges_btn') : __('admin.resources.user.actions.enable_download_privileges_btn'))
//            ->modalHeading($this->getUserRecord()->enabled == 'yes' ? __('admin.resources.user.actions.disable_modal_title') : __('admin.resources.user.actions.enable_modal_title'))
            ->requiresConfirmation()
            ->action(function () {
                $userRep = $this->getRep();
                try {
                    $userRep->updateDownloadPrivileges($this->currentUser(), $this->getUserRecord()->id, $this->getUserRecord()->downloadpos == 'yes' ? 'no' : 'yes');
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    protected function buildEnableDisableUploadPrivilegesAction(): Action
    {
        return Action::make($this->getUserRecord()->uploadpos == 'yes' ? __('admin.resources.user.actions.disable_upload_privileges_btn') : __('admin.resources.user.actions.enable_upload_privileges_btn'))
            ->requiresConfirmation()
            ->action(function () {
                $userRep = $this->getRep();
                try {
                    $userRep->updateUploadPrivileges($this->currentUser(), $this->getUserRecord()->id, $this->getUserRecord()->uploadpos == 'yes' ? 'no' : 'yes');
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    protected function buildEnableDisableForumPostAction(): Action
    {
        return Action::make($this->getUserRecord()->forumpost == 'yes' ? __('admin.resources.user.actions.disable_forumpost_btn') : __('admin.resources.user.actions.enable_forumpost_btn'))
            ->requiresConfirmation()
            ->action(function () {
                $userRep = $this->getRep();
                try {
                    $userRep->updateForumPost($this->currentUser(), $this->getUserRecord()->id, $this->getUserRecord()->forumpost == 'yes' ? 'no' : 'yes');
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    protected function buildWarnAction(): Action
    {
        $record = $this->getUserRecord();
        $isWarned = $record->warned === 'yes';
        return Action::make($isWarned ? __('admin.resources.user.actions.edit_warning_btn') : __('admin.resources.user.actions.warn_btn'))
            ->icon('heroicon-o-exclamation-triangle')
            ->color($isWarned ? 'danger' : 'warning')
            ->schema([
                Select::make('weeks')
                    ->label(__('admin.resources.user.actions.warn_duration'))
                    ->options([
                        0 => __('admin.resources.user.actions.warn_remove'),
                        1 => '1 ' . __('admin.resources.user.actions.warn_week'),
                        2 => '2 ' . __('admin.resources.user.actions.warn_weeks'),
                        4 => '4 ' . __('admin.resources.user.actions.warn_weeks'),
                        8 => '8 ' . __('admin.resources.user.actions.warn_weeks'),
                        255 => __('admin.resources.user.actions.warn_indefinite'),
                    ])
                    ->default($isWarned ? 0 : 1)
                    ->required(),
                TextInput::make('reason')
                    ->label(__('admin.resources.user.actions.warn_reason'))
                    ->placeholder(__('admin.resources.user.actions.warn_reason_placeholder')),
            ])
            ->action(function (array $data) {
                $userRep = $this->getRep();
                try {
                    $userRep->warnUser($this->currentUser(), $this->getUserRecord()->id, (int) $data['weeks'], (string) ($data['reason'] ?? ''));
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildGrantPropsAction(): Action
    {
        return Action::make(__('admin.resources.user.actions.grant_prop_btn'))
            ->modalHeading(__('admin.resources.user.actions.grant_prop_btn'))
            ->schema([
                Select::make('meta_key')
                    ->options(UserMeta::listProps())
                    ->label(__('admin.resources.user.actions.grant_prop_form_prop'))->required(),
                TextInput::make('duration')->label(__('admin.resources.user.actions.grant_prop_form_duration'))
                    ->helperText(__('admin.resources.user.actions.grant_prop_form_duration_help')),

            ])
            ->action(function ($data) {
                $rep = $this->getRep();
                try {
                    $rep->addMeta($this->getUserRecord(), $data, $data);
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildDeleteAction(): DeleteAction
    {
        return DeleteAction::make()->using(function () {
            $this->getRep()->destroy($this->getUserRecord()->id);
            return redirect(self::$resource::getUrl('index'));
        });
    }

    public function getViewData(): array
    {
        return [
            'props' => $this->listUserProps(),
            'temporary_invite_count' => $this->countTemporaryInvite()
        ];
    }

    /**
     * @return array<int, string>
     */
    private function listUserProps(): array
    {
        $metaKeys = [
            UserMeta::META_KEY_PERSONALIZED_USERNAME,
            UserMeta::META_KEY_CHANGE_USERNAME,
        ];
        $metaList = $this->getRep()->listMetas($this->getUserRecord()->id, $metaKeys);
        $props = [];
        foreach ($metaList as $metaKey => $metas) {
            $meta = $metas->first();
            if (! $meta instanceof UserMeta) {
                continue;
            }
            $text = sprintf('[%s]', $meta->metaKeyText);
            if ($meta->meta_key == UserMeta::META_KEY_PERSONALIZED_USERNAME) {
                $text .= sprintf('(%s)', $meta->getDeadlineText());
            }
            $props[] = "<div>{$text}</div>";
        }
        return $props;
    }

    private function countTemporaryInvite(): int
    {
        return Invite::query()->where('inviter', $this->getUserRecord()->id)
            ->where('invitee', '')
            ->whereNotNull('expired_at')
            ->where('expired_at', '>', Carbon::now())
            ->count();
    }

    protected function buildChangeClassAction(): Action
    {
        return Action::make('change_class')
            ->label(__('admin.resources.user.actions.change_class_btn'))
            ->schema([
                Select::make('class')
                    ->options(User::listClass(User::CLASS_PEASANT, $this->currentUser()->class - 1))
                    ->default($this->getUserRecord()->class)
                    ->label(__('user.labels.class'))
                    ->required()
                    ->reactive()
                ,
                Radio::make('vip_added')
                    ->options(self::getYesNoOptions('yes', 'no'))
                    ->default($this->getUserRecord()->vip_added)
                    ->label(__('user.labels.vip_added'))
                    ->helperText(__('user.labels.vip_added_help'))
                    ->hidden(fn (Get $get) => $get('class') != User::CLASS_VIP)
                ,
                DateTimePicker::make('vip_until')
                    ->default($this->getUserRecord()->vip_until)
                    ->label(__('user.labels.vip_until'))
                    ->helperText(__('user.labels.vip_until_help'))
                    ->hidden(fn (Get $get) => $get('class') != User::CLASS_VIP)
                ,
                TextInput::make('reason')
                    ->label(__('admin.resources.user.actions.enable_disable_reason'))
                    ->placeholder(__('admin.resources.user.actions.enable_disable_reason_placeholder'))
                ,
            ])
            ->action(function ($data) {
                $userRep = $this->getRep();
                try {
                    $userRep->changeClass($this->currentUser(), $this->getUserRecord(), $data['class'], $data['reason'], $data);
                    $this->sendSuccessNotification();
                } catch (Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function sendSuccessNotification(string $msg = ""): void
    {
        Notification::make()
            ->success()
            ->title($msg ?: "Success!")
            ->send()
        ;
    }

    private function sendFailNotification(string $msg = ""): void
    {
        Notification::make()
            ->danger()
            ->title($msg ?: "Fail!")
            ->send()
        ;
    }
}
