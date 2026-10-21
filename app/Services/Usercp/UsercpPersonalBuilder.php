<?php

declare(strict_types=1);

namespace App\Services\Usercp;

use App\Contracts\Repositories\UsercpLookupRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Enums\UserAcceptPms;
use App\Enums\UserGender;
use App\Models\TrackerUrl;
use App\Models\User;
use App\Support\Config\SiteConfig;
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

    public function build(ActorContext $actor): UsercpPersonalSection
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

        $notifs = (string) $actor->value('notifs', '');
        $notifCheckboxes = [];
        foreach (User::$notificationOptions as $option) {
            $notifCheckboxes[] = [
                'name' => 'notifs['.$option.']',
                'checked' => is_null($actor->value('notifs', null)) || str_contains($notifs, "[{$option}]"),
                'label' => (string) __('usercp.checkbox_pm_on_'.$option),
            ];
        }

        return new UsercpPersonalSection(
            formId: 'form'.Strings::randomCode(6),
            parked: $actor->yes('parked'),
            acceptpms: UserAcceptPms::tryFrom((int) $actor->value('acceptpms', 0))?->stringValue() ?? 'yes',
            deletepms: $actor->yes('deletepms'),
            savepms: $actor->yes('savepms'),
            commentpm: $actor->yes('commentpm'),
            notifCheckboxes: $notifCheckboxes,
            gender: UserGender::tryFrom((int) $actor->value('gender', 2))?->stringValue() ?? 'N/A',
            trackerUrlId: (string) $actor->value('tracker_url_id', ''),
            trackerUrlOptions: $trackerUrlOptions,
            country: (string) $actor->value('country', ''),
            countryOptions: $countryOptions,
            avatar: (string) $actor->value('avatar', ''),
            defaultAvatarUrl: $defaultAvatarUrl,
            bitbucketOptions: $bitbucketOptions,
            enableBitbucket: SiteConfig::current()->main->enableBitbucket(),
            info: (string) $actor->value('info', ''),
        );
    }
}
