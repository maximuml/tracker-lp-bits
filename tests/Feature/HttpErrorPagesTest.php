<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * HTTP error pages must render inside the site chrome (header/footer,
 * theme, nav) instead of the bare framework page, while JSON clients
 * keep receiving JSON errors.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class HttpErrorPagesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unknown_path_renders_themed_404_for_guests(): void
    {
        $response = $this->get('/web/definitely-not-a-real-page-'.bin2hex(random_bytes(4)));

        $response->assertNotFound();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('data-chrome="modern"', $html);
        $this->assertStringContainsString('nxm-header', $html);
        $this->assertStringContainsString('nx-error', $html);
        $this->assertStringContainsString('Page not found', $html);
        $this->assertStringContainsString('/web/index', $html);
    }

    public function test_unknown_path_renders_themed_404_for_users(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/web/definitely-not-a-real-page-'.bin2hex(random_bytes(4)));

        $response->assertNotFound();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('nx-error', $html);
        $this->assertStringContainsString('Page not found', $html);
    }

    public function test_json_client_gets_json_404(): void
    {
        $response = $this->getJson('/web/definitely-not-a-real-page-'.bin2hex(random_bytes(4)));

        $response->assertNotFound();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
        $this->assertStringNotContainsString('nxm-header', (string) $response->getContent());
    }

    public function test_error_views_exist_for_all_mapped_statuses(): void
    {
        foreach (['403', '404', '500', '503'] as $status) {
            $this->assertTrue(
                view()->exists('errors.'.$status),
                "errors/{$status}.blade.php missing"
            );
        }
    }
}
