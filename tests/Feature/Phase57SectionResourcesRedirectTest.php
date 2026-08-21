<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 5.7: verify that the legacy catmanage/forummanage/moforums/fields/formats/videoformats
 * endpoints redirect to the corresponding Filament Section resources.
 */
final class Phase57SectionResourcesRedirectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
    }

    public function testCatmanageRedirectsToCategoryResource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/catmanage');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/section/categories');
    }

    public function testForummanageRedirectsToForumResource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/forummanage');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/section/forums');
    }

    public function testMoforumsRedirectsToOverForumResource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/moforums');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/section/over-forums');
    }

    public function testFieldsRedirectsToTorrentCustomFieldResource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/fields');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/torrent-custom-fields');
    }

    public function testFormatsRedirectsToCodecResource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/formats');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/section/codecs');
    }

    public function testVideoformatsRedirectsToStandardResource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/videoformats');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/section/standards');
    }
}
