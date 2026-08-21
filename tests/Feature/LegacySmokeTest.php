<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke-test the legacy URL surface after the public/*.php entry-point cleanup.
 *
 * These tests exercise the in-process Laravel router, not the Docker web
 * server, so they can run in the standard `php artisan test` pipeline.
 * Pages that need per-page legacy language files are covered by the
 * CriticalPathTest end-to-end suite instead.
 */
final class LegacySmokeTest extends TestCase
{
    public function test_public_legacy_pages_are_reachable(): void
    {
        $public = [
            '/login',
            '/signup',
            '/recover',
        ];

        foreach ($public as $path) {
            $response = $this->get($path);
            $response->assertStatus(200, "Expected 200 for {$path}, got ".$response->getStatusCode());
        }
    }

    public function test_unauthenticated_legacy_pages_redirect_to_login(): void
    {
        $protected = [
            '/index',
            '/torrents',
            '/details/1',
            '/usercp',
            '/userdetails?id=1',
            '/forums',
            '/friends',
            '/messages',
            '/topten',
        ];

        foreach ($protected as $path) {
            $response = $this->get($path);
            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 401], true),
                "Expected redirect or 401 for {$path}, got ".$response->getStatusCode()
            );
        }
    }

    public function test_root_redirects(): void
    {
        $this->get('/')->assertStatus(302);
    }

    public function test_health_endpoint(): void
    {
        $this->get('/health')->assertJson(['status' => 'ok']);
    }
}
