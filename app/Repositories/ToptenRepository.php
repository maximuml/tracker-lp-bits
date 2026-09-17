<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Config\SiteConfig;
use App\Support\Settings;

final class ToptenRepository
{
    public function __construct(
        private readonly Topten\UserSections $userSections = new Topten\UserSections,
        private readonly Topten\TorrentSections $torrentSections = new Topten\TorrentSections,
        private readonly Topten\CountrySections $countrySections = new Topten\CountrySections,
        private readonly Topten\CommunitySections $communitySections = new Topten\CommunitySections,
        private readonly Topten\OtherSections $otherSections = new Topten\OtherSections,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(int $type, int $limit, ?string $subtype): array
    {
        $type = in_array($type, [1, 2, 3, 5, 6], true) ? $type : 1;
        $limit = $limit < 1 || $limit > 250 ? 10 : $limit;

        $enabledDonation = ((string) Settings::get('main.donation', 'no')) === 'yes';
        $dateFounded = SiteConfig::current()->tweak->dateFounded();

        $sections = match ($type) {
            1 => $this->userSections->build($limit, $subtype),
            2 => $this->torrentSections->build($limit, $subtype),
            3 => $this->countrySections->build($limit, $subtype),
            5 => $this->communitySections->build($limit, $subtype),
            6 => $this->otherSections->build($limit, $subtype, $enabledDonation),
        };

        return [
            'type' => $type,
            'limit' => $limit,
            'subtype' => $subtype,
            'enabledDonation' => $enabledDonation,
            'dateFounded' => $dateFounded,
            'sections' => $sections,
        ];
    }
}
