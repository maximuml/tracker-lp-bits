<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserClass;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verify that legacy redirect stubs in routes/legacy/auth.php
 * redirect to the correct Filament URLs.
 *
 * Step 28 — URL stability contract: legacy URLs that were migrated
 * to Filament must continue to redirect to the right destination.
 */
final class LegacyRedirectStubsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['class' => UserClass::SYSOP->value]);
        $this->actingAs($admin, 'nexus-web');
    }

    #[DataProvider("provideRedirectStubs")]
    public function test_legacy_redirect_stubs_return_302(string $legacyPath, string $expectedTarget): void
    {
        $response = $this->get($legacyPath);

        $this->assertSame(
            302,
            $response->getStatusCode(),
            "Expected 302 for {$legacyPath}, got ".$response->getStatusCode()
        );

        $location = $response->headers->get('Location', '');
        $this->assertStringContainsString(
            $expectedTarget,
            $location,
            "Expected {$legacyPath} to redirect to contain {$expectedTarget}, got {$location}"
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideRedirectStubs(): array
    {
        return [
            // Phase 5.3: bans/cheaters/ipcheck
            'bans' => ['/bans', '/nexusphp/security/bans'],
            'cheaterbox' => ['/cheaterbox', '/nexusphp/security/cheaters'],
            'cheaters' => ['/cheaters', '/nexusphp/security/cheaters'],
            'ipcheck' => ['/ipcheck', '/nexusphp/users'],
            'ipsearch' => ['/ipsearch', '/nexusphp/users'],

            // Phase 5.4: staffbox
            'staffbox' => ['/staffbox', '/nexusphp/security/staff-messages'],

            // Phase 5.5: stats/allagents
            'stats' => ['/stats', '/nexusphp'],
            'allagents' => ['/allagents', '/nexusphp'],

            // Phase 5.2: donorlist/warned (redirect with query filters)
            'donorlist' => ['/donorlist', '/nexusphp/users?tableFilters'],
            'warned' => ['/warned', '/nexusphp/users?tableFilters'],

            // Phase 5.7: section management
            'catmanage' => ['/catmanage', '/nexusphp/section/categories'],
            'forummanage' => ['/forummanage', '/nexusphp/section/forums'],
            'moforums' => ['/moforums', '/nexusphp/section/over-forums'],
            'fields' => ['/fields', '/nexusphp/torrent-custom-fields'],
            'formats' => ['/formats', '/nexusphp/section/codecs'],
            'videoformats' => ['/videoformats', '/nexusphp/section/standards'],

            // Phase 5.6: system actions
            'delacctadmin' => ['/delacctadmin', '/nexusphp/system-actions'],
            'deletedisabled' => ['/deletedisabled', '/nexusphp/system-actions'],
            'massmail' => ['/massmail', '/nexusphp/system-actions'],

            // Phase 5.6: maxlogin
            'maxlogin' => ['/maxlogin', '/nexusphp/login-attempts'],
        ];
    }

    #[DataProvider("provideRedirectStubsWithId")]
    public function test_legacy_redirect_stubs_with_id_param(string $legacyPath, string $expectedTarget): void
    {
        $response = $this->get($legacyPath);

        $this->assertSame(
            302,
            $response->getStatusCode(),
            "Expected 302 for {$legacyPath}, got ".$response->getStatusCode()
        );

        $location = $response->headers->get('Location', '');
        $this->assertStringContainsString(
            $expectedTarget,
            $location,
            "Expected {$legacyPath} to redirect to contain {$expectedTarget}, got {$location}"
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideRedirectStubsWithId(): array
    {
        return [
            'iphistory with id' => ['/iphistory?id=42', '/nexusphp/users/42'],
            'iphistory without id' => ['/iphistory', '/nexusphp/users'],
            'checkuser with id' => ['/checkuser?id=42', '/nexusphp/users/42'],
            'checkuser without id' => ['/checkuser', '/nexusphp/users'],
        ];
    }
}
