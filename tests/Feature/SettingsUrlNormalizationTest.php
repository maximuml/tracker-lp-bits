<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserClass;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
class SettingsUrlNormalizationTest extends TestCase
{
    use DatabaseTransactions;

    private function sysop(): User
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::SYSOP->value]);

        return $user;
    }

    public function test_basic_section_normalizes_host_only_baseurl(): void
    {
        $sysop = $this->sysop();

        $response = $this->withNexusCookie($sysop)->post('/web/settings/submit', [
            'action' => 'savesettings_basic',
            'SITENAME' => 'Test Tracker',
            'BASEURL' => 'example.com/',
            'announce_url' => 'https://announce.example.com/announce.php',
        ]);

        $response->assertRedirect('/settings.php?action=basicsettings');
        $this->assertSame('http://example.com', DB::table('settings')->where('name', 'basic.BASEURL')->value('value'));
        $this->assertSame('https://announce.example.com/announce.php', DB::table('settings')->where('name', 'basic.announce_url')->value('value'));
    }

    public function test_basic_section_rejects_invalid_url_and_keeps_old_value(): void
    {
        Settings::saveBatch('basic', ['BASEURL' => 'https://good.example.com']);
        Settings::resetCache();
        $sysop = $this->sysop();

        $response = $this->withNexusCookie($sysop)->post('/web/settings/submit', [
            'action' => 'savesettings_basic',
            'SITENAME' => 'Test Tracker',
            'BASEURL' => 'javascript://alert(1)',
            'announce_url' => 'https://announce.example.com/announce.php',
        ]);

        $response->assertOk();
        $response->assertSeeText('Invalid URL');
        $this->assertSame('https://good.example.com', DB::table('settings')->where('name', 'basic.BASEURL')->value('value'));
    }

    public function test_security_section_normalizes_https_announce_url(): void
    {
        $sysop = $this->sysop();

        $response = $this->withNexusCookie($sysop)->post('/web/settings/submit', [
            'action' => 'savesettings_security',
            'https_announce_url' => 'tracker.example.com/announce.php',
        ]);

        $response->assertRedirect();
        $this->assertSame('http://tracker.example.com/announce.php', DB::table('settings')->where('name', 'security.https_announce_url')->value('value'));
    }

    public function test_basic_section_rejects_non_sysop(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::USER->value]);

        $response = $this->withNexusCookie($user)->post('/web/settings/submit', [
            'action' => 'savesettings_basic',
            'SITENAME' => 'Test Tracker',
            'BASEURL' => 'example.com',
            'announce_url' => 'https://announce.example.com/announce.php',
        ]);

        $response->assertSeeText('Permission denied');
    }
}
