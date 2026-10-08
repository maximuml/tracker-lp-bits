<?php

declare(strict_types=1);

namespace App\Services\Usercp;

use App\Contracts\Repositories\UsercpLookupRepositoryInterface;
use App\Enums\UserAcceptPms;
use App\Enums\UserGender;
use App\Models\TrackerUrl;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\RequestValues;
use App\Support\Strings;
use App\Support\Url;
use App\ViewModels\Usercp\UsercpPersonalSection;

/**
 * Builds the usercp "personal" settings section.
 */
final class UsercpPersonalBuilder
{
    public function __construct(
        private readonly UsercpLookupRepositoryInterface $usercpLookupRepository
    ) {}

    public function build(CurrentUser $curUser): UsercpPersonalSection
    {
        $countryOptions = ['0' => '---- '.__('usercp.select_none_selected').' ----'];
        foreach ($this->usercpLookupRepository->getCountryOptions() as $ct) {
            $countryOptions[(string) $ct->id] = (string) $ct->name;
        }

        $trackerUrlOptions = [];
        foreach (TrackerUrl::listAll() as $item) {
            $trackerUrlOptions[(string) $item->id] = (string) $item->url;
        }

        $baseUrl = Url::absolute(SiteConfig::current()->basic->baseUrl() ?: RequestValues::serverValue('HTTP_HOST', 'localhost'));
        $defaultAvatarUrl = $baseUrl.'/pic/default_avatar.png';
        $bitbucketOptions = [];
        foreach ($this->usercpLookupRepository->getBitbucketOptions() as $sor) {
            $bitbucketOptions[$baseUrl.'/bitbucket/'.(string) $sor->name] = (string) $sor->name;
        }

        $notifs = (string) $curUser->value('notifs', '');
        $notifCheckboxes = [];
        foreach (User::$notificationOptions as $option) {
            $notifCheckboxes[] = [
                'name' => 'notifs['.$option.']',
                'checked' => is_null($curUser->value('notifs', null)) || str_contains($notifs, "[{$option}]"),
                'label' => (string) __('usercp.checkbox_pm_on_'.$option),
            ];
        }

        return new UsercpPersonalSection(
            formId: 'form'.Strings::randomCode(6),
            parked: $curUser->yes('parked'),
            acceptpms: UserAcceptPms::tryFrom((int) $curUser->value('acceptpms', 0))?->stringValue() ?? 'yes',
            deletepms: $curUser->yes('deletepms'),
            savepms: $curUser->yes('savepms'),
            commentpm: $curUser->yes('commentpm'),
            notifCheckboxes: $notifCheckboxes,
            gender: UserGender::tryFrom((int) $curUser->value('gender', 2))?->stringValue() ?? 'N/A',
            trackerUrlId: (string) $curUser->value('tracker_url_id', ''),
            trackerUrlOptions: $trackerUrlOptions,
            country: (string) $curUser->value('country', ''),
            countryOptions: $countryOptions,
            avatar: (string) $curUser->value('avatar', ''),
            defaultAvatarUrl: $defaultAvatarUrl,
            bitbucketOptions: $bitbucketOptions,
            enableBitbucket: SiteConfig::current()->main->enableBitbucket(),
            info: (string) $curUser->value('info', ''),
        );
    }
}
