<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Usercp;

use App\Models\Passkey;
use App\Repositories\UserPasskeyRepository;
use App\Services\Usercp\UsercpSecurityBuilder;
use App\ViewModels\Usercp\PasskeyItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Kills escaped mutants in the security-section builder (formerly
 * UsercpPageService::buildSecuritySection + buildPasskeyItems): confirm
 * hidden-field capture, saved-flag message composition, two-step state,
 * passkey AAGUID metadata resolution.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UsercpSecurityBuilderTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    /** @var UserPasskeyRepository&MockInterface */
    private UserPasskeyRepository $passkeyRepository;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var UserPasskeyRepository&MockInterface $passkeyRepository */
        $passkeyRepository = Mockery::mock(UserPasskeyRepository::class);
        $this->passkeyRepository = $passkeyRepository;
        $this->seedTestSettings([
            'disableemailchange' => 'yes',
            'smtptype' => 'internal',
            'SITENAME' => 'TestSite',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function builder(): UsercpSecurityBuilder
    {
        return new UsercpSecurityBuilder($this->passkeyRepository);
    }

    /** @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $post */
    private function bindRequest(array $params = [], array $post = []): void
    {
        $req = Request::create('/usercp?'.http_build_query($params), 'POST');
        $req->request->replace($post);
        $this->app->instance('request', $req);
    }

    private function emptyPasskeys(): void
    {
        $this->passkeyRepository->shouldReceive('getList')->andReturn(collect());
    }

    public function test_confirm_step_captures_posted_hidden_fields(): void
    {
        $this->bindRequest([], [
            'resetpasskey' => '1',
            'resetauthkey' => '',
            'email' => ' new@x.com ',
            'chpassword' => 'pw',
            'privacy' => 'strong',
            'two_step_secret' => 'SECRET',
            'two_step_code' => '123456',
        ]);

        $s = $this->builder()->build(['id' => 1, 'privacy' => 1, 'email' => 'old@x.com'], 'save');

        $this->assertTrue($s->isConfirm);
        $this->assertSame('1', $s->confirmHidden['resetpasskey']);
        $this->assertSame('new@x.com', $s->confirmHidden['email']);
        $this->assertSame('pw', $s->confirmHidden['chpassword']);
        $this->assertSame('strong', $s->confirmHidden['privacy']);
        $this->assertSame('SECRET', $s->confirmHidden['two_step_secret']);
        $this->assertSame('123456', $s->confirmHidden['two_step_code']);
        $this->assertSame([], $s->passkeys);
    }

    public function test_non_confirm_step_has_empty_hidden_and_passkeys_list(): void
    {
        $this->bindRequest();
        $this->emptyPasskeys();
        $this->passkeyRepository->shouldReceive('getAaguids')->andReturn([]);

        $s = $this->builder()->build(['id' => 1, 'privacy' => 0, 'email' => 'a@b.c', 'two_step_secret' => 'EX'], '');

        $this->assertFalse($s->isConfirm);
        $this->assertSame([], $s->confirmHidden);
        $this->assertSame([], $s->passkeys);
        $this->assertSame('strong', $s->privacy);
        $this->assertSame('a@b.c', $s->email);
        $this->assertTrue($s->twoStep->hasSecret);
    }

    public function test_saved_message_appends_sentence_per_flag(): void
    {
        $this->bindRequest();
        $this->emptyPasskeys();

        $base = $this->builder()->build(['id' => 1], '')->savedMessage;
        $this->assertNotEmpty($base);

        $sentences = [
            'mail' => (string) __('usercp.std_confirmation_email_sent'),
            'passkey' => (string) __('usercp.std_passkey_reset'),
            'password' => (string) __('usercp.std_password_changed'),
            'privacy' => (string) __('usercp.std_privacy_level_updated'),
        ];
        foreach ($sentences as $flag => $sentence) {
            $this->bindRequest([$flag => '1']);
            $msg = $this->builder()->build(['id' => 1], '')->savedMessage;
            $this->assertSame($base.' '.$sentence, $msg);
        }
    }

    public function test_saved_flags_reflect_query_params(): void
    {
        $this->bindRequest(['passkey' => '1']);
        $this->emptyPasskeys();

        $s = $this->builder()->build(['id' => 1], '');

        $this->assertSame(
            ['mail' => false, 'passkey' => true, 'password' => false, 'privacy' => false],
            $s->savedFlags,
        );
    }

    public function test_passkey_items_resolve_aaguid_metadata(): void
    {
        $this->bindRequest();

        $pk = new Passkey;
        $pk->credential_id = 'cred-abc';
        $pk->aaguid = '00112233445566778899aabbccddeeff';
        $pk->created_at = '2024-01-02 03:04:05';

        $this->passkeyRepository->shouldReceive('getList')->andReturn(collect([$pk]));
        $this->passkeyRepository->shouldReceive('getAaguids')->andReturn([
            '00112233-4455-6677-8899-aabbccddeeff' => ['name' => 'Bitwarden', 'icon_dark' => 'ico.png'],
        ]);

        $s = $this->builder()->build(['id' => 1, 'privacy' => 1], '');

        $this->assertCount(1, $s->passkeys);
        $item = $s->passkeys[0];
        $this->assertInstanceOf(PasskeyItem::class, $item);
        $this->assertSame('cred-abc', $item->credentialId);
        $this->assertSame('ico.png', $item->iconUrl);
        $this->assertSame('Bitwarden', $item->iconAlt);
        $this->assertSame('Bitwarden', $item->displayName);
        $this->assertTrue($item->showCredentialId);
        $this->assertSame('2024-01-02 03:04:05', $item->createdAt->format('Y-m-d H:i:s'));
    }

    public function test_passkey_items_fall_back_to_defaults_without_metadata(): void
    {
        $this->bindRequest();

        $pk = new Passkey;
        $pk->credential_id = 'cred-xyz';
        $pk->aaguid = 'ffffffffffffffffffffffffffffffff';
        $pk->created_at = '2024-05-06 07:08:09';

        $this->passkeyRepository->shouldReceive('getList')->andReturn(collect([$pk]));
        $this->passkeyRepository->shouldReceive('getAaguids')->andReturn([]);

        $s = $this->builder()->build(['id' => 1, 'privacy' => 1], '');

        $item = $s->passkeys[0];
        $this->assertSame(UserPasskeyRepository::DEFAULT_ICON, $item->iconUrl);
        $this->assertSame('cred-xyz', $item->displayName);
        $this->assertFalse($item->showCredentialId);
    }

    public function test_email_change_visibility_follows_settings(): void
    {
        $this->bindRequest();
        $this->emptyPasskeys();

        $this->assertTrue($this->builder()->build(['id' => 1, 'privacy' => 1], '')->showEmailChange);

        $this->seedTestSettings(['smtptype' => 'none']);
        $this->assertFalse($this->builder()->build(['id' => 1, 'privacy' => 1], '')->showEmailChange);
    }
}
