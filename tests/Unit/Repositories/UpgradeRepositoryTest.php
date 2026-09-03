<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\BaseRepository;
use App\Repositories\UpgradeRepository;
use Tests\TestCase;

final class UpgradeRepositoryTest extends TestCase
{
    public function test_is_instantiable(): void
    {
        $repo = new UpgradeRepository;

        $this->assertInstanceOf(UpgradeRepository::class, $repo);
    }

    public function test_extends_base_repository(): void
    {
        $repo = new UpgradeRepository;

        $this->assertInstanceOf(BaseRepository::class, $repo);
    }

    public function test_datetime_invalid_value_fields_constant_structure(): void
    {
        $expected = [
            'comments' => ['editdate'],
            'invites' => ['time_invited'],
            'offers' => ['allowedtime'],
            'peers' => ['last_action', 'prev_action'],
            'posts' => ['editdate'],
            'snatched' => ['last_action', 'completedat'],
            'torrents' => ['last_action', 'promotion_until', 'last_reseed'],
            'users' => [
                'last_login', 'last_access', 'last_home', 'last_offer', 'forum_access', 'last_staffmsg',
                'last_pm', 'last_comment', 'last_post', 'donoruntil', 'warneduntil', 'noaduntil', 'vip_until',
                'leechwarnuntil', 'lastwarned',
            ],
        ];

        $this->assertSame($expected, UpgradeRepository::DATETIME_INVALID_VALUE_FIELDS);
    }

    public function test_datetime_invalid_value_fields_contains_torrent_last_action(): void
    {
        $this->assertContains('last_action', UpgradeRepository::DATETIME_INVALID_VALUE_FIELDS['torrents']);
    }

    public function test_datetime_invalid_value_fields_contains_user_warneduntil(): void
    {
        $this->assertContains('warneduntil', UpgradeRepository::DATETIME_INVALID_VALUE_FIELDS['users']);
    }
}
