<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Phase 5.6: verify that the legacy delacctadmin/deletedisabled/massmail/maxlogin
 * endpoints redirect to the Filament SystemActions page and LoginAttemptResource.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class DestructiveActionsRedirectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
    }

    public function test_delacctadmin_redirects_to_system_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/delacctadmin');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/system-actions');
    }

    public function test_deletedisabled_redirects_to_system_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/deletedisabled');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/system-actions');
    }

    public function test_massmail_redirects_to_system_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/massmail');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/system-actions');
    }

    public function test_maxlogin_redirects_to_login_attempt_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/maxlogin');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/security/login-attempts');
    }

    public function test_legacy_take_uris_redirect_to_rest_endpoints(): void
    {
        $user = User::factory()->create();

        foreach ([
            ['/takeflush', '/web/torrents/flush'],
            ['/takereseed', '/web/torrents/reseed'],
            ['/fastdelete', '/web/torrents/fast-delete'],
            ['/delete', '/web/torrents/delete'],
            ['/takeinvite', '/web/invites/send'],
            ['/takeamountupload', '/web/system/amount-upload'],
            ['/takeupdate', '/web/system/update'],
            ['/take-increment-bulk', '/web/system/increment-bulk'],
            ['/docleanup', '/web/system/cleanup'],
            ['/mailtest', '/web/system/mail-test'],
            ['/clearcache', '/web/system/clear-cache'],
            ['/location', '/web/system/location'],
            ['/testip', '/web/system/test-ip'],
            ['/user-ban-log', '/web/admin/user-ban-log'],
            ['/reset', '/web/admin/users/reset'],
            ['/self-enable', '/web/admin/users/self-enable'],
            ['/unco', '/web/admin/users/unco'],
            ['/adduser', '/web/admin/users/add'],
            ['/bitbucketlog', '/web/admin/bitbucket-log'],
            ['/donated', '/web/info/donated'],
            ['/thanks', '/web/torrents/thanks'],
            ['/downloadnotice', '/web/torrents/download-notice'],
            ['/magic', '/web/bonus/magic'],
            ['/freeleech', '/web/bonus/freeleech'],
            ['/attendance', '/web/user/attendance'],
            ['/report', '/web/reports/create'],
            ['/getrss', '/web/rss/generate'],
            ['/preview', '/web/preview'],
            ['/notifications', '/web/notifications/mark-read'],
            ['/attachment', '/web/attachments/upload'],
        ] as [$uri, $target]) {
            $response = $this->withNexusCookie($user)->post($uri, ['id' => '1']);
            $response->assertStatus(308);
            $this->assertStringEndsWith($target, (string) $response->headers->get('Location'));
        }
    }
}
