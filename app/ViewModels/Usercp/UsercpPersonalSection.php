<?php

declare(strict_types=1);

namespace App\ViewModels\Usercp;

/**
 * Personal settings section of the user control panel.
 *
 * Option lists are `value => label` maps ready for `x-settings-select`;
 * `notifCheckboxes` carries the dynamic per-option PM checkboxes.
 */
final readonly class UsercpPersonalSection
{
    /**
     * @param  array<int|string, string>  $countryOptions  id => name
     * @param  array<string, string>  $trackerUrlOptions  id => url
     * @param  array<string, string>  $bitbucketOptions  url => name
     * @param  list<array{name: string, checked: bool, label: string}>  $notifCheckboxes
     */
    public function __construct(
        public string $formId,
        public bool $parked,
        public string $acceptpms,
        public bool $deletepms,
        public bool $savepms,
        public bool $commentpm,
        public array $notifCheckboxes,
        public string $gender,
        public string $trackerUrlId,
        public array $trackerUrlOptions,
        public string $country,
        public array $countryOptions,
        public string $avatar,
        public string $defaultAvatarUrl,
        public array $bitbucketOptions,
        public bool $enableBitbucket,
        public string $info,
    ) {}
}
