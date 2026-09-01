<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Wave 2 Step 7: Private attachment serving.
 *
 * Attachments must only be accessible through the authenticated
 * /getattachment controller (which checks dlkey + path traversal),
 * not via direct nginx URLs. The nginx /attachments/ location is
 * marked `internal` so only X-Accel-Redirect can reach it.
 */
final class PrivateAttachmentsTest extends TestCase
{
    /**
     * The nginx config marks /attachments/ as internal.
     */
    public function test_nginx_attachments_location_is_internal(): void
    {
        $conf = file_get_contents(base_path('.docker/openresty/sites/app.conf.template'));
        $this->assertStringContainsString('location /attachments/', $conf);
        $this->assertStringContainsString('internal;', $conf, 'nginx /attachments/ must be internal (X-Accel-Redirect only)');
    }

    /**
     * The controller uses X-Accel-Redirect to serve files.
     */
    public function test_controller_uses_x_accel_redirect(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/UtilityController.php'));
        $this->assertStringContainsString('X-Accel-Redirect', $source, 'getattachment must use X-Accel-Redirect');
    }

    /**
     * The controller checks dlkey (not just id).
     */
    public function test_controller_checks_dlkey(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/UtilityController.php'));
        $this->assertStringContainsString('dlkey', $source, 'getattachment must verify dlkey');
        $this->assertStringContainsString("where('dlkey'", $source, 'getattachment must query by dlkey');
    }

    /**
     * The controller has path traversal protection.
     */
    public function test_controller_has_path_traversal_protection(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/UtilityController.php'));
        $this->assertStringContainsString('realpath', $source, 'getattachment must use realpath()');
        $this->assertStringContainsString('str_starts_with', $source, 'getattachment must verify file is within base path');
    }

    /**
     * The controller sets X-Content-Type-Options: nosniff.
     */
    public function test_controller_sets_nosniff_header(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/UtilityController.php'));
        $this->assertStringContainsString('nosniff', $source, 'getattachment must set X-Content-Type-Options: nosniff');
    }

    /**
     * The controller sets Content-Disposition: attachment.
     */
    public function test_controller_sets_content_disposition_attachment(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/UtilityController.php'));
        $this->assertStringContainsString('Content-Disposition', $source);
        $this->assertStringContainsString('attachment;', $source, 'getattachment must force download (Content-Disposition: attachment)');
    }

    /**
     * dlkey is generated with CSPRNG, not md5(microtime).
     */
    public function test_dlkey_uses_csprng_not_md5_microtime(): void
    {
        $source = file_get_contents(app_path('Services/AttachmentMutationService.php'));
        $this->assertStringContainsString('random_bytes', $source, 'dlkey must use CSPRNG (random_bytes)');
        $this->assertStringNotContainsString('md5($location.microtime', $source, 'dlkey must not use md5(microtime) — predictable');
    }

    /**
     * /getattachment route requires authentication (auth.nexus middleware).
     */
    public function test_getattachment_route_requires_auth(): void
    {
        $route = null;
        foreach (app('router')->getRoutes() as $r) {
            if ($r->uri() === 'getattachment') {
                $route = $r;
                break;
            }
        }
        $this->assertNotNull($route, '/getattachment route must exist');
        $middleware = $route->gatherMiddleware();
        $this->assertTrue(
            in_array('auth.nexus:nexus-web', $middleware) || in_array('auth.nexus', $middleware),
            '/getattachment must require authentication'
        );
    }

    /**
     * getattachment with invalid id returns 400/401/302.
     */
    public function test_getattachment_with_invalid_id_returns_error(): void
    {
        $response = $this->get('/getattachment?id=0&dlkey=');
        // Should not be 200 — invalid id/dlkey
        $this->assertContains($response->status(), [400, 401, 302]);
    }

    /**
     * getattachment with valid format but non-existent dlkey returns 404/401/302.
     */
    public function test_getattachment_with_nonexistent_dlkey_returns_404(): void
    {
        $response = $this->get('/getattachment?id=99999&dlkey=nonexistentkey1234567890123456');
        // 401/302 if auth fails first, 404 if auth passes but record not found
        $this->assertContains($response->status(), [404, 401, 302]);
    }
}
