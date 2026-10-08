<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Installer;

use App\Services\Installer\UpgradeSchemaChecks;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UpgradeSchemaChecksTest extends TestCase
{
    public function test_peers_unique_index_detected_on_migrated_schema(): void
    {
        $this->assertTrue($this->checks()->peersHasUniqueTorrentPeerUser());
    }

    public function test_snatched_unique_index_detected_on_migrated_schema(): void
    {
        $this->assertTrue($this->checks()->isSnatchedTableTorrentUserUnique());
    }

    public function test_column_info_returns_metadata_and_null_for_missing(): void
    {
        $checks = $this->checks();

        $id = $checks->columnInfo('users', 'id');
        $this->assertNotNull($id);
        $this->assertSame('id', $id['name']);
        $this->assertTrue($id['auto_increment'] ?? false);

        $this->assertNull($checks->columnInfo('users', 'no_such_column'));
        $this->assertNull($checks->columnInfo('no_such_table', 'id'));
    }

    private function checks(): UpgradeSchemaChecks
    {
        return new UpgradeSchemaChecks;
    }
}
