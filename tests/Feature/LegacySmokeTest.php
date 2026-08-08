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
    public function testPublicLegacyPagesAreReachable(): void
    {
        $public = [
            '/login',
            '/signup',
            '/recover',
        ];

        foreach ($public as $path) {
            $response = $this->get($path);
            $response->assertStatus(200, "Expected 200 for {$path}, got " . $response->getStatusCode());
        }
    }

    public function testUnauthenticatedLegacyPagesRedirectToLogin(): void
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
                "Expected redirect or 401 for {$path}, got " . $response->getStatusCode()
            );
        }
    }

    public function testRootRedirects(): void
    {
        $this->get('/')->assertStatus(302);
    }

    public function testHealthEndpoint(): void
    {
        $this->get('/health')->assertJson(['status' => 'ok']);
    }
}
