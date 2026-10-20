<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class UsercpSecurityLayoutTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Settings::saveBatch('security', ['changeemail' => 'yes']);
        Settings::saveBatch('smtp', ['smtptype' => 'internal']);
        Settings::resetCache();
    }

    protected function tearDown(): void
    {
        Settings::resetCache();
        parent::tearDown();
    }

    public function test_security_field_owner_names_match_the_pre_redesign_baselines(): void
    {
        $emailUser = $this->makeUser(['two_step_secret' => '']);
        $emailResponse = $this->withNexusCookie($emailUser)->get('/usercp?action=security');
        $emailResponse->assertOk();
        $this->assertSame(
            ['action', 'chpassword', 'email', 'privacy', 'privacy', 'privacy', 'resetpasskey', 'two_step_code', 'two_step_secret', 'type'],
            $this->securityFormNames($this->responseHtml($emailResponse)),
            'Security field names for a user without 2FA and with email changes enabled',
        );

        $twoStepUser = $this->makeUser(['two_step_secret' => 'JBSWY3DPEHPK3PXP']);
        $twoStepResponse = $this->withNexusCookie($twoStepUser)->get('/usercp?action=security');
        $twoStepResponse->assertOk();
        $this->assertSame(
            ['action', 'chpassword', 'email', 'privacy', 'privacy', 'privacy', 'resetpasskey', 'two_step_code', 'type'],
            $this->securityFormNames($this->responseHtml($twoStepResponse)),
            'Security field names for a user with 2FA enabled',
        );

        $confirmUser = $this->makeUser();
        $confirmResponse = $this->withNexusCookie($confirmUser)->post('/web/usercp/security', [
            'action' => 'security',
            'type' => 'save',
            'chpassword' => 'new-secret-pass',
            'passagain' => 'new-secret-pass',
        ]);
        $this->assertSame(200, $confirmResponse->getStatusCode(), 'Security confirm mode must render for the baseline');
        $this->assertSame(
            ['action', 'chpassword', 'email', 'privacy', 'response', 'two_step_code', 'two_step_secret', 'type', 'username'],
            $this->securityFormNames($this->responseHtml($confirmResponse)),
            'Security field names for confirm mode',
        );
    }

    public function test_saved_security_page_uses_a_success_status(): void
    {
        $response = $this->withNexusCookie($this->makeUser())
            ->get('/usercp?action=security&type=saved');

        $response->assertOk()
            ->assertSee('role="status"', false)
            ->assertSee('Settings saved.')
            ->assertDontSee('Saved!');
    }

    public function test_security_sections_are_present_in_their_modes(): void
    {
        $normalResponse = $this->withNexusCookie($this->makeUser())
            ->get('/usercp?action=security');
        $normalResponse->assertOk();
        foreach (['password', 'privacy', 'two-step', 'passkeys', 'danger'] as $section) {
            $normalResponse->assertSee('id="ucp-sec-'.$section.'"', false);
        }

        $confirmResponse = $this->withNexusCookie($this->makeUser())->post('/web/usercp/security', [
            'action' => 'security',
            'type' => 'save',
            'chpassword' => 'new-secret-pass',
            'passagain' => 'new-secret-pass',
        ]);
        $confirmResponse->assertOk()->assertSee('id="ucp-sec-confirm"', false);
    }

    public function test_logout_all_button_uses_its_separate_csrf_form(): void
    {
        $response = $this->withNexusCookie($this->makeUser())
            ->get('/usercp?action=security');
        $response->assertOk();
        $xpath = $this->documentXPath($this->responseHtml($response));

        $buttons = $xpath->query('//button[@type="submit" and @form="usercp-logout-all"]');
        $forms = $xpath->query('//form[@id="usercp-logout-all"]');
        $this->assertNotFalse($buttons);
        $this->assertNotFalse($forms);
        $this->assertSame(1, $buttons->length);
        $this->assertSame(1, $forms->length);

        $form = $forms->item(0);
        $this->assertInstanceOf(DOMElement::class, $form);
        $this->assertSame(route('usercp.logout-all'), $form->getAttribute('action'));
        $tokens = $xpath->query('.//input[@name="_token"]', $form);
        $this->assertNotFalse($tokens);
        $this->assertSame(1, $tokens->length);
    }

    public function test_passkey_buttons_are_outside_the_security_form(): void
    {
        $response = $this->withNexusCookie($this->makeUser())
            ->get('/usercp?action=security');
        $response->assertOk();
        $xpath = $this->documentXPath($this->responseHtml($response));

        $buttons = $xpath->query('//button[@type="button" and (@id="passkey_create" or @data-passkey-id)]');
        $this->assertNotFalse($buttons);
        $this->assertGreaterThan(0, $buttons->length);
        foreach ($buttons as $button) {
            $this->assertInstanceOf(DOMElement::class, $button);
            $this->assertNotSame('security', $button->getAttribute('form'));
            $owners = $xpath->query('ancestor::form[@id="security"]', $button);
            $this->assertNotFalse($owners);
            $this->assertSame(0, $owners->length);
        }
    }

    /** @param TestResponse<SymfonyResponse> $response */
    private function responseHtml(TestResponse $response): string
    {
        $content = $response->getContent();
        if (! is_string($content)) {
            throw new \RuntimeException('Could not read the Security response.');
        }

        return $content;
    }

    /**
     * @return list<string>
     */
    private function securityFormNames(string $html): array
    {
        $xpath = $this->documentXPath($html);
        $elements = $xpath->query('//*[@name and (ancestor::form[@id="security"] or @form="security")]');
        if ($elements === false) {
            throw new \RuntimeException('Could not inspect the Security form.');
        }
        $names = [];
        foreach ($elements as $element) {
            $this->assertInstanceOf(DOMElement::class, $element);
            $names[] = $element->getAttribute('name');
        }
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
    }

    private function documentXPath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
