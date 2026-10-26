<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels;

use App\Enums\Permission\PermissionEnum;
use App\Services\PermissionChecker;
use App\Support\Html\SafeHtml;
use App\ViewModels\Torrent\PeerTableFactory;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression: peer IP addresses must never reach the page for viewers
 * lacking the confidential-info right. The enablelocation tweak used to
 * be compared with === 'yes' while arriving as bool — falling into the
 * raw-IP branch for every viewer.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class PeerTableFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param  array<string, bool>  $permissions
     */
    private function factory(array $permissions = []): PeerTableFactory
    {
        $checker = Mockery::mock(PermissionChecker::class);
        $checker->shouldReceive('userCan')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static fn (string $perm): bool => $permissions[$perm] ?? false);

        return new PeerTableFactory($checker);
    }

    /**
     * @return array<string, mixed>
     */
    private function peerRow(int $peerId = 7, int $userId = 42): array
    {
        return [
            'id' => $peerId,
            'userid' => $userId,
            'st' => time() - 3600,
            'la' => time(),
            'uploaded' => 1024,
            'downloaded' => 512,
            'uploadoffset' => 0,
            'downloadoffset' => 0,
            'to_go' => 0,
            'seeder' => 'yes',
            'finishedat' => time() - 1800,
            'connectable' => 'yes',
            'agent' => 'qBittorrent/4.5',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function build(PeerTableFactory $factory, array $rows, mixed $enablelocation, array $peerIpInfo, array $curUser): object
    {
        return $factory->buildTable(
            'Peers',
            $rows,
            ['anonymous' => 'no', 'owner' => 1, 'size' => 2048],
            [],
            true,
            $enablelocation,
            $peerIpInfo,
            [42 => SafeHtml::fromPlainText('peer_user'), 99 => SafeHtml::fromPlainText('self')],
            $curUser
        );
    }

    public function test_location_tweak_shows_public_geo_not_raw_ip(): void
    {
        $table = $this->build(
            $this->factory(),
            [$this->peerRow()],
            true,
            [7 => [['ip' => '203.0.113.9', 'public' => 'Moscow, RU', 'mod' => 'mod-info']]],
            ['id' => 99]
        );

        $row = $table->rows[0];
        $this->assertSame(['Moscow, RU'], $row->locationLines);
        $this->assertSame('', $row->locationTitle);
        $this->assertStringNotContainsString('203.0.113.9', (string) $row->locationTitle);
    }

    public function test_location_tweak_bool_true_does_not_leak_ip_lines(): void
    {
        // The historical bug: a bool tweak compared with 'yes' landed in
        // the raw-IP fallback for everyone.
        $table = $this->build(
            $this->factory(),
            [$this->peerRow()],
            true,
            [7 => [['ip' => '203.0.113.9', 'public' => 'Moscow, RU', 'mod' => 'mod-info']]],
            ['id' => 99]
        );

        $this->assertNotContains('203.0.113.9', $table->rows[0]->locationLines);
    }

    public function test_confidential_viewer_sees_ip_in_title(): void
    {
        $table = $this->build(
            $this->factory([PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO->value => true]),
            [$this->peerRow()],
            true,
            [7 => [['ip' => '203.0.113.9', 'public' => 'Moscow, RU', 'mod' => 'mod-info']]],
            ['id' => 99]
        );

        $this->assertStringContainsString('203.0.113.9', (string) $table->rows[0]->locationTitle);
    }

    public function test_owner_peer_sees_own_ip_in_title(): void
    {
        $table = $this->build(
            $this->factory(),
            [$this->peerRow()],
            true,
            [7 => [['ip' => '203.0.113.9', 'public' => 'Moscow, RU', 'mod' => 'mod-info']]],
            ['id' => 42]
        );

        $this->assertStringContainsString('203.0.113.9', (string) $table->rows[0]->locationTitle);
    }

    public function test_tweak_off_shows_ip_lines_for_column_viewers(): void
    {
        // Tweak disabled: the location column itself is only rendered for
        // confidential viewers, so the lines carry the raw IPs.
        $table = $this->build(
            $this->factory([PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO->value => true]),
            [$this->peerRow()],
            false,
            [7 => [['ip' => '203.0.113.9', 'public' => 'Moscow, RU', 'mod' => 'mod-info']]],
            ['id' => 99]
        );

        $this->assertSame(['203.0.113.9'], $table->rows[0]->locationLines);
        $this->assertNull($table->rows[0]->locationTitle);
    }
}
