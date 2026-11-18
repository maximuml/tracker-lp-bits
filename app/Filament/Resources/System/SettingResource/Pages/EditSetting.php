<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\SettingResource\Pages;

use App\Enums\UserClass as UserClassEnum;
use App\Filament\OptionsTrait;
use App\Filament\Resources\System\SettingResource;
use App\Models\HitAndRun;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\TokenRepository;
use App\Repositories\ToolRepository;
use App\Support\Admin;
use App\Support\Cache;
use App\Support\Settings;
use App\Support\Url;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class EditSetting extends Page implements HasForms
{
    use InteractsWithForms, OptionsTrait;

    protected static string $resource = SettingResource::class;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    private function contentSchema(): Schema
    {
        $content = $this->getSchema('content');
        if (! $content instanceof Schema) {
            throw new \RuntimeException('Content schema not initialized.');
        }

        return $content;
    }

    private TokenRepository $tokenRepository;

    private ToolRepository $toolRepository;

    public function boot(
        TokenRepository $tokenRepository,
        ToolRepository $toolRepository,
    ): void {
        $this->tokenRepository = $tokenRepository;
        $this->toolRepository = $toolRepository;
    }

    public function getTitle(): string
    {
        return __('label.setting.nav_text');
    }

    public function mount(): void
    {
        static::authorizeResourceAccess();
        $this->fillForm();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data');
    }

    private function fillForm(): void
    {
        $settings = Settings::fromDb();

        $fallbackEnabled = (bool) config('captcha.attendance.enabled', true);
        $rawSetting = Arr::get($settings, 'captcha.attendance.enabled', $fallbackEnabled);
        if (is_string($rawSetting)) {
            $normalized = in_array(strtolower($rawSetting), ['1', 'true', 'yes'], true);
        } else {
            $normalized = (bool) $rawSetting;
        }
        Arr::set($settings, 'captcha.attendance.enabled', $normalized);

        // Legacy DBs may hold 'Yes'/'No'/'YES' while Radio/Select options are
        // lowercase 'yes'/'no' — the implicit in-rule would otherwise block
        // every save. Normalize case so existing installs keep working.
        array_walk_recursive($settings, static function (mixed &$value): void {
            if (is_string($value) && in_array(strtolower($value), ['yes', 'no'], true)) {
                $value = strtolower($value);
            }
        });

        $this->contentSchema()->fill($settings);
    }

    /** @return array<Component|Action> */
    protected function getFormSchema(): array
    {
        return [
            Tabs::make('Heading')
                ->tabs($this->getTabs()),
            Action::make('submit')
                ->label(__('label.save'))
                ->action(fn () => $this->submit()),
        ];
    }

    public function submit(): void
    {
        static::authorizeResourceAccess();

        $formData = $this->contentSchema()->getState();
        $notAutoloadNames = ['donation_custom'];
        $data = [];
        foreach ($formData as $prefix => $parts) {
            foreach ($parts as $name => $value) {
                if (in_array($name, $notAutoloadNames)) {
                    $autoload = false;
                } else {
                    $autoload = true;
                }
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                // *.base_url fields (image hosting drivers) share the site
                // URL contract — canonical scheme://host[:port][/path].
                if ($name === 'base_url' && is_string($value) && $value !== '') {
                    $value = Url::normalize($value) ?? $value;
                }
                $data[] = [
                    'name' => "$prefix.$name",
                    'value' => $value,
                    'autoload' => $autoload,
                ];

            }
        }
        Setting::query()->upsert($data, ['name'], ['value']);
        Setting::query()->whereIn('name', [
            'captcha.driver',
            'captcha.turnstile',
            'captcha.recaptcha',
        ])->delete();
        $this->doAfterUpdate();
        Cache::clearSettings();
        Admin::successNotification('');
    }

    /**
     * this actions get config must not use cache !!!
     */
    private function doAfterUpdate(): void
    {
        Setting::updateUserTokenPermissionAllowedCache();
    }

    /** @return array<Tab> */
    private function getTabs(): array
    {
        $tabs = [];
        $tabs[] = Tab::make(__('label.setting.hr.tab_header'))
            ->id('hr')
            ->schema($this->getHitAndRunSchema())
            ->columns(2);

        $tabs[] = Tab::make(__('label.setting.backup.tab_header'))
            ->id('backup')
            ->schema([
                Radio::make('backup.enabled')->options(self::$yesOrNo)->inline(true)->label(__('label.enabled'))->helperText(__('label.setting.backup.enabled_help')),
                Radio::make('backup.frequency')->options(['daily' => 'daily', 'hourly' => 'hourly'])->inline(true)->label(__('label.setting.backup.frequency'))->helperText(__('label.setting.backup.frequency_help')),
                Select::make('backup.hour')->options(array_map(fn (int $hour): string => (string) $hour, range(0, 23)))->label(__('label.setting.backup.hour'))->helperText(__('label.setting.backup.hour_help')),
                Select::make('backup.minute')->options(array_map(fn (int $minute): string => (string) $minute, range(0, 59)))->label(__('label.setting.backup.minute'))->helperText(__('label.setting.backup.minute_help')),
                TextInput::make('backup.export_path')->label(__('label.setting.backup.export_path'))->helperText(new HtmlString(__('label.setting.backup.export_path_help', ['default_path' => $this->toolRepository->getBackupExportPathDefault()]))),
                TextInput::make('backup.retention_count')->numeric()->label(__('label.setting.backup.retention_count'))->helperText(new HtmlString(__('label.setting.backup.retention_count_help', ['default_count' => ToolRepository::BACKUP_RETENTION_COUNT_DEFAULT]))),
                Radio::make('backup.via_ftp')->options(self::$yesOrNo)->inline(true)->label(__('label.setting.backup.via_ftp'))->helperText(new HtmlString(e(__('label.setting.backup.via_ftp_help')).' <a href="https://laravel.com/docs/master/filesystem#ftp-driver-configuration">Laravel doc</a>')),
                Radio::make('backup.via_sftp')->options(self::$yesOrNo)->inline(true)->label(__('label.setting.backup.via_sftp'))->helperText(new HtmlString(e(__('label.setting.backup.via_sftp_help')).' <a href="https://laravel.com/docs/master/filesystem#sftp-driver-configuration">Laravel doc</a>')),
                Radio::make('backup.via_gdrive')->options(self::$yesOrNo)->inline(true)->label(__('label.setting.backup.via_gdrive'))->helperText(new HtmlString(__('label.setting.backup.via_gdrive_help'))),
            ])->columns(2);

        $id = 'meilisearch';
        $tabs[] = Tab::make(__("label.setting.$id.tab_header"))
            ->id($id)
            ->schema($this->getTabMeilisearchSchema($id))
            ->columns(2);

        $id = 'image_hosting';
        $tabs[] = Tab::make(__("label.setting.$id.tab_header"))
            ->id($id)
            ->schema($this->getTabImageHostingSchema($id))
            ->columns(2);
        $id = 'permission';
        $tabs[] = Tab::make(__("label.setting.$id.tab_header"))
            ->id($id)
            ->schema($this->getTabPermissionSchema($id))
            ->columns(2);

        $tabs[] = Tab::make(__('label.setting.captcha.tab_header'))
            ->id('captcha')
            ->schema($this->getTabCaptchaSchema())
            ->columns(2);

        $tabs[] = Tab::make(__('label.setting.system.tab_header'))
            ->id('system')
            ->schema([
                Radio::make('system.change_username_card_allow_characters_outside_the_alphabets')
                    ->options(self::$yesOrNo)
                    ->inline(true)
                    ->label(__('label.setting.system.change_username_card_allow_characters_outside_the_alphabets')),
                TextInput::make('system.change_username_min_interval_in_days')
                    ->integer()
                    ->label(__('label.setting.system.change_username_min_interval_in_days')),
                TextInput::make('system.cookie_valid_days')
                    ->integer()
                    ->label(__('label.setting.system.cookie_valid_days')),
                TextInput::make('system.maximum_upload_speed')
                    ->integer()
                    ->label(__('label.setting.system.maximum_upload_speed'))
                    ->helperText(__('label.setting.system.maximum_upload_speed_help')),
                Radio::make('system.is_invite_pre_email_and_username')
                    ->options(self::$yesOrNo)
                    ->inline(true)
                    ->label(__('label.setting.system.is_invite_pre_email_and_username'))
                    ->helperText(__('label.setting.system.is_invite_pre_email_and_username_help')),
                Select::make('system.access_admin_class_min')
                    ->options(User::listClass(UserClassEnum::VIP->value))
                    ->label(__('label.setting.system.access_admin_class_min'))
                    ->helperText(__('label.setting.system.access_admin_class_min_help')),
                TextInput::make('system.alarm_email_receiver')
                    ->label(__('label.setting.system.alarm_email_receiver'))
                    ->helperText(__('label.setting.system.alarm_email_receiver_help')),
            ])->columns(2);

        return $tabs;
    }

    /** @return array<Component> */
    private function getTabCaptchaSchema(): array
    {
        $captchaPrefix = 'captcha';

        $driverOptions = [
            'image' => __('label.setting.captcha.drivers.image'),
        ];

        $defaultDriver = Settings::get('captcha.default');
        if ($defaultDriver === null || ! array_key_exists($defaultDriver, $driverOptions)) {
            $defaultDriver = 'image';
        }

        $attendanceCaptchaSetting = Settings::get('captcha.attendance.enabled', true);
        if (is_string($attendanceCaptchaSetting)) {
            $attendanceCaptchaEnabled = in_array(strtolower($attendanceCaptchaSetting), ['1', 'true', 'yes'], true);
        } else {
            $attendanceCaptchaEnabled = (bool) $attendanceCaptchaSetting;
        }

        $schema = [
            Toggle::make("$captchaPrefix.attendance.enabled")
                ->label(__('label.setting.captcha.attendance.enabled'))
                ->helperText(__('label.setting.captcha.attendance.enabled_help'))
                ->default($attendanceCaptchaEnabled)
                ->columnSpanFull(),
            Select::make("$captchaPrefix.default")
                ->options($driverOptions)
                ->label(__('label.setting.captcha.driver'))
                ->helperText(__('label.setting.captcha.driver_help'))
                ->default($defaultDriver),
        ];

        return $schema;
    }

    /** @return array<Component> */
    private function getHitAndRunSchema(): array
    {
        $default = [
            Radio::make('hr.mode')->options(HitAndRun::listModes(true))->inline(true)->label(__('label.setting.hr.mode')),
            TextInput::make('hr.inspect_time')->helperText(__('label.setting.hr.inspect_time_help'))->label(__('label.setting.hr.inspect_time'))->integer(),
            TextInput::make('hr.seed_time_minimum')->helperText(__('label.setting.hr.seed_time_minimum_help'))->label(__('label.setting.hr.seed_time_minimum'))->integer(),
            TextInput::make('hr.leech_time_minimum')->helperText(__('label.setting.hr.leech_time_minimum_help'))->label(__('label.setting.hr.leech_time_minimum'))->integer(),
            TextInput::make('hr.ignore_when_ratio_reach')->helperText(__('label.setting.hr.ignore_when_ratio_reach_help'))->label(__('label.setting.hr.ignore_when_ratio_reach'))->integer(),
            TextInput::make('hr.ban_user_when_counts_reach')->helperText(__('label.setting.hr.ban_user_when_counts_reach_help'))->label(__('label.setting.hr.ban_user_when_counts_reach'))->integer(),
            TextInput::make('hr.include_rate')->helperText(__('label.setting.hr.include_rate_help'))->label(__('label.setting.hr.include_rate'))->numeric(),
        ];

        return $default;
    }

    /** @return array<Component> */
    private function getTabMeilisearchSchema(string $id): array
    {
        $schema = [];

        $name = "$id.enabled";
        $schema[] = Radio::make($name)
            ->options(self::$yesOrNo)
            ->inline(true)
            ->label(__('label.enabled'))
            ->helperText(__("label.setting.{$name}_help"));

        $name = "$id.search_description";
        $schema[] = Radio::make($name)
            ->options(self::$yesOrNo)
            ->inline(true)
            ->label(__("label.setting.$name"))
            ->helperText(__("label.setting.{$name}_help"));

        $name = "$id.default_search_mode";
        $schema[] = Radio::make($name)
            ->options(SearchBox::listSearchModes())
            ->inline(true)
            ->label(__("label.setting.$name"))
            ->helperText(__("label.setting.{$name}_help"));

        return $schema;
    }

    /** @return array<Component> */
    private function getTabImageHostingSchema(string $id): array
    {
        $schema = [];
        $name = "$id.driver";
        $schema[] = Radio::make($name)
            ->options(['local' => 'local'])
            ->inline(true)
            ->label(__("label.setting.$name"))
            ->helperText(__("label.setting.{$name}_help"))
            ->columnSpanFull();

        return $schema;
    }

    /** @return array<Component> */
    private function getTabPermissionSchema(string $id): array
    {
        $schema = [];

        $name = "$id.user_token_allowed";
        $schema[] = CheckboxList::make($name)
            ->options($this->tokenRepository->listUserTokenPermissions())
            ->label(__("label.setting.{$name}"))
            ->helperText(__("label.setting.{$name}_help"))
            ->columns(2);

        return $schema;
    }
}
