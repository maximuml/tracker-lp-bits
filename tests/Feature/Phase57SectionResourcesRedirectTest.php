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

    public function test_catmanage_redirects_to_category_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/catmanage');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/section/categories');
    }

    public function test_forummanage_redirects_to_forum_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/forummanage');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/section/forums');
    }

    public function test_moforums_redirects_to_over_forum_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/moforums');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/section/over-forums');
    }

    public function test_fields_redirects_to_torrent_custom_field_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/fields');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/torrent-custom-fields');
    }

    public function test_formats_redirects_to_codec_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/formats');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/section/codecs');
    }

    public function test_videoformats_redirects_to_standard_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/videoformats');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/section/standards');
    }
}
