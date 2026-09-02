<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Wave 5 Step 18: Production Docker image and split compose files.
 *
 * Verifies that:
 * - docker-compose.yml is a base file (no bind-mounts)
 * - docker-compose.dev.yml has dev-only overrides (bind-mounts)
 * - docker-compose.prod.yml has prod-only overrides (read-only, named volumes)
 * - Dockerfile.prod is multi-stage with USER www-data
 * - entrypoint.prod.sh does not run composer install
 * - .dockerignore excludes dev-only files from prod image
 */
final class ProdImageTest extends TestCase
{
    /**
     * Base docker-compose.yml does not contain bind-mounts (moved to dev override).
     */
    public function test_base_compose_has_no_bind_mounts(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));
        // The base file should not bind-mount the source code
        $this->assertStringNotContainsString('- .:/var/www/html', $compose, 'Base compose must not bind-mount source (dev override)');
    }

    /**
     * Dev override file exists and bind-mounts source code.
     */
    public function test_dev_override_has_bind_mounts(): void
    {
        $dev = file_get_contents(base_path('docker-compose.dev.yml'));
        $this->assertStringContainsString('- .:/var/www/html', $dev, 'Dev override must bind-mount source code');
        $this->assertStringContainsString('openresty', $dev, 'Dev override must mount openresty config');
    }

    /**
     * Prod override file exists and uses read-only rootfs.
     */
    public function test_prod_override_has_read_only_rootfs(): void
    {
        $prod = file_get_contents(base_path('docker-compose.prod.yml'));
        $this->assertStringContainsString('read_only: true', $prod, 'Prod override must use read-only rootfs');
        $this->assertStringContainsString('user: www-data', $prod, 'Prod override must run as www-data');
        $this->assertStringContainsString('Dockerfile.prod', $prod, 'Prod override must reference Dockerfile.prod');
        // No bind-mounts of source code in prod
        $this->assertStringNotContainsString('- .:/var/www/html', $prod, 'Prod override must not bind-mount source');
    }

    /**
     * Prod override uses named volumes for writable directories.
     */
    public function test_prod_override_has_named_volumes(): void
    {
        $prod = file_get_contents(base_path('docker-compose.prod.yml'));
        $this->assertStringContainsString('storage-data', $prod, 'Prod must have storage volume');
        $this->assertStringContainsString('bootstrap-cache', $prod, 'Prod must have bootstrap/cache volume');
        $this->assertStringContainsString('attachments-data', $prod, 'Prod must have attachments volume');
        $this->assertStringContainsString('torrents-data', $prod, 'Prod must have torrents volume');
    }

    /**
     * Dockerfile.prod is multi-stage (has AS clauses).
     */
    public function test_dockerfile_prod_is_multi_stage(): void
    {
        $dockerfile = file_get_contents(base_path('.docker/php/Dockerfile.prod'));
        $this->assertStringContainsString('AS ext-builder', $dockerfile, 'Must have ext-builder stage');
        $this->assertStringContainsString('AS vendor-builder', $dockerfile, 'Must have vendor-builder stage');
        $this->assertStringContainsString('AS assets-builder', $dockerfile, 'Must have assets-builder stage');
        $this->assertStringContainsString('AS production', $dockerfile, 'Must have production stage');
    }

    /**
     * Dockerfile.prod runs as non-root user.
     */
    public function test_dockerfile_prod_runs_as_www_data(): void
    {
        $dockerfile = file_get_contents(base_path('.docker/php/Dockerfile.prod'));
        $this->assertStringContainsString('USER www-data', $dockerfile, 'Prod image must run as www-data');
    }

    /**
     * Dockerfile.prod installs composer --no-dev and classmap-authoritative.
     */
    public function test_dockerfile_prod_no_dev_classmap(): void
    {
        $dockerfile = file_get_contents(base_path('.docker/php/Dockerfile.prod'));
        $this->assertStringContainsString('--no-dev', $dockerfile, 'Prod must install --no-dev');
        $this->assertStringContainsString('--classmap-authoritative', $dockerfile, 'Prod must use classmap-authoritative');
    }

    /**
     * Dockerfile.prod builds Laravel caches at build time.
     */
    public function test_dockerfile_prod_builds_laravel_caches(): void
    {
        $dockerfile = file_get_contents(base_path('.docker/php/Dockerfile.prod'));
        $this->assertStringContainsString('config:cache', $dockerfile, 'Prod must build config cache at build time');
        $this->assertStringContainsString('route:cache', $dockerfile, 'Prod must build route cache at build time');
        $this->assertStringContainsString('view:cache', $dockerfile, 'Prod must build view cache at build time');
    }

    /**
     * Dockerfile.prod uses the production entrypoint (not the dev one).
     */
    public function test_dockerfile_prod_uses_prod_entrypoint(): void
    {
        $dockerfile = file_get_contents(base_path('.docker/php/Dockerfile.prod'));
        $this->assertStringContainsString('entrypoint.prod.sh', $dockerfile, 'Prod must use entrypoint.prod.sh');
    }

    /**
     * Production entrypoint does not run composer install.
     */
    public function test_prod_entrypoint_no_composer_install(): void
    {
        $entrypoint = file_get_contents(base_path('.docker/php/entrypoint.prod.sh'));
        // Strip comments before checking
        $lines = array_filter(explode("\n", $entrypoint), fn ($line) => ! str_starts_with(ltrim($line), '#'));
        $code = implode("\n", $lines);
        $this->assertStringNotContainsString('composer install', $code, 'Prod entrypoint must not run composer install');
        $this->assertStringNotContainsString('cp -r "$SOURCE_DIR"', $code, 'Prod entrypoint must not copy install scripts');
    }

    /**
     * Production entrypoint waits for MySQL and Redis.
     */
    public function test_prod_entrypoint_waits_for_deps(): void
    {
        $entrypoint = file_get_contents(base_path('.docker/php/entrypoint.prod.sh'));
        $this->assertStringContainsString('wait_for_service', $entrypoint, 'Prod entrypoint must wait for services');
        $this->assertStringContainsString('mysql', $entrypoint, 'Prod entrypoint must wait for MySQL');
        $this->assertStringContainsString('redis', $entrypoint, 'Prod entrypoint must wait for Redis');
    }

    /**
     * .dockerignore excludes dev-only files from prod image.
     */
    public function test_dockerignore_excludes_dev_files(): void
    {
        $ignore = file_get_contents(base_path('.dockerignore'));
        $this->assertStringContainsString('/tests', $ignore, '.dockerignore must exclude tests/');
        $this->assertStringContainsString('/.github', $ignore, '.dockerignore must exclude .github/');
        $this->assertStringContainsString('/.agents', $ignore, '.dockerignore must exclude .agents/');
        // Dev entrypoint and opcache.dev.ini must NOT be excluded (dev Dockerfile needs them)
        $this->assertStringNotContainsString('/.docker/php/entrypoint.sh', $ignore, '.dockerignore must not exclude dev entrypoint.sh');
        $this->assertStringNotContainsString('/.docker/php/opcache.dev.ini', $ignore, '.dockerignore must not exclude opcache.dev.ini');
    }

    /**
     * CI workflows use dev compose overrides via COMPOSE_FILE env.
     */
    public function test_ci_uses_dev_compose_overrides(): void
    {
        $ci = file_get_contents(base_path('.github/workflows/ci.yml'));
        $this->assertStringContainsString('COMPOSE_FILE=docker-compose.yml:docker-compose.dev.yml', $ci, 'CI must set COMPOSE_FILE for dev overrides');
    }

    /**
     * README documents both dev and prod compose usage.
     */
    public function test_readme_documents_prod_compose(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $this->assertStringContainsString('docker-compose.dev.yml', $readme, 'README must document dev compose');
        $this->assertStringContainsString('docker-compose.prod.yml', $readme, 'README must document prod compose');
        $this->assertStringContainsString('Dockerfile.prod', $readme, 'README must reference Dockerfile.prod');
    }
}
