<?php

declare(strict_types=1);

namespace App\Services\Usercp;

use App\Contracts\Repositories\UsercpLookupRepositoryInterface;
use App\Enums\UserAcceptPms;
use App\Enums\UserGender;
use App\Models\TrackerUrl;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Input;
use App\Support\Strings;
use App\Support\Url;
use App\Support\YesNo;
use App\ViewModels\Usercp\UsercpPersonalSection;

/**
 * Builds the usercp "personal" settings section.
 */
final class UsercpPersonalBuilder
{
    public function __construct(
        private readonly UsercpLookupRepositoryInterface $usercpLookupRepository
    ) {}

    /**
     * @param  array<string, mixed>  $curUser
     */
    public function build(array $curUser): UsercpPersonalSection
    {
        $countryOptions = ['0' => '---- '.__('legacy/usercp.select_none_selected').' ----'];
        foreach ($this->usercpLookupRepository->getCountryOptions() as $ct) {
            $countryOptions[(string) $ct->id] = (string) $ct->name;
        }

        $trackerUrlOptions = [];
        foreach (TrackerUrl::listAll() as $item) {
            $trackerUrlOptions[(string) $item->id] = (string) $item->url;
        }

        $baseUrl = Url::absolute(SiteConfig::current()->basic->baseUrl() ?: Input::serverValue('HTTP_HOST', 'localhost'));
        $defaultAvatarUrl = $baseUrl.'/pic/default_avatar.png';
        $bitbucketOptions = [];
        foreach ($this->usercpLookupRepository->getBitbucketOptions() as $sor) {
            $bitbucketOptions[$baseUrl.'/bitbucket/'.(string) $sor->name] = (string) $sor->name;
        }

        $notifs = (string) ($curUser['notifs'] ?? '');
        $notifCheckboxes = [];
        foreach (User::$notificationOptions as $option) {
            $notifCheckboxes[] = [
                'name' => 'notifs['.$option.']',
                'checked' => is_null($curUser['notifs'] ?? null) || str_contains($notifs, "[{$option}]"),
                'label' => (string) __('legacy/usercp.checkbox_pm_on_'.$option),
            ];
        }

        return new UsercpPersonalSection(
            formId: 'form'.Strings::randomCode(6),
            parked: YesNo::isYes($curUser['parked'] ?? null),
            acceptpms: UserAcceptPms::tryFrom((int) ($curUser['acceptpms'] ?? 0))?->stringValue() ?? 'yes',
            deletepms: YesNo::isYes($curUser['deletepms'] ?? null),
            savepms: YesNo::isYes($curUser['savepms'] ?? null),
            commentpm: YesNo::isYes($curUser['commentpm'] ?? null),
            notifCheckboxes: $notifCheckboxes,
            gender: UserGender::tryFrom((int) ($curUser['gender'] ?? 2))?->stringValue() ?? 'N/A',
            trackerUrlId: (string) ($curUser['tracker_url_id'] ?? ''),
            trackerUrlOptions: $trackerUrlOptions,
            country: (string) ($curUser['country'] ?? ''),
            countryOptions: $countryOptions,
            avatar: (string) ($curUser['avatar'] ?? ''),
            defaultAvatarUrl: $defaultAvatarUrl,
            bitbucketOptions: $bitbucketOptions,
            enableBitbucket: SiteConfig::current()->main->enableBitbucket(),
            info: (string) ($curUser['info'] ?? ''),
        );
    }
}
