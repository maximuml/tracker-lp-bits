<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Wave 2 Step 10: Redis auth, mandatory compose secrets, and
 * insecure-default warnings in the installer.
 *
 * docker-compose.yml must require all secrets via ${VAR:?} syntax
 * (refuses to start without them), Redis must require a password,
 * and .env.example must not ship with real passwords as defaults.
 */
final class ComposeSecretsTest extends TestCase
{
    /**
     * docker-compose.yml requires REDIS_PASSWORD via :? syntax.
     */
    public function test_compose_requires_redis_password(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));
        $this->assertStringContainsString('REDIS_PASSWORD:?REDIS_PASSWORD is required', $compose, 'docker-compose must require REDIS_PASSWORD');
        $this->assertStringContainsString('--requirepass', $compose, 'Redis must use --requirepass');
    }

    /**
     * docker-compose.yml requires DB_PASSWORD via :? syntax.
     */
    public function test_compose_requires_db_password(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));
        $this->assertStringContainsString('DB_PASSWORD:?DB_PASSWORD is required', $compose, 'docker-compose must require DB_PASSWORD');
    }

    /**
     * docker-compose.yml requires MEILISEARCH_MASTER_KEY via :? syntax.
     */
    public function test_compose_requires_meili_master_key(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));
        $this->assertStringContainsString('MEILISEARCH_MASTER_KEY:?MEILISEARCH_MASTER_KEY is required', $compose, 'docker-compose must require MEILISEARCH_MASTER_KEY');
    }

    /**
     * Redis healthcheck uses the password for authentication.
     */
    public function test_redis_healthcheck_uses_password(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));
        $this->assertStringContainsString('redis-cli', $compose);
        $this->assertStringContainsString('-a', $compose);
        $this->assertStringContainsString('REDIS_PASSWORD', $compose);
    }

    /**
     * .env.example does not ship with a real DB password as default.
     */
    public function test_env_example_does_not_use_real_db_password(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertStringNotContainsString('DB_PASSWORD=nexusphp', $envExample, '.env.example must not ship with DB_PASSWORD=nexusphp as a default');
        $this->assertStringContainsString('DB_PASSWORD=', $envExample, '.env.example must have a DB_PASSWORD entry');
    }

    /**
     * .env.example does not ship with a real Meili master key.
     */
    public function test_env_example_does_not_use_real_meili_key(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertStringNotContainsString('MEILISEARCH_MASTER_KEY=nexusphp_default_key', $envExample, '.env.example must not ship with a real Meili master key');
    }

    /**
     * The installer warns when insecure default secrets are detected.
     */
    public function test_installer_has_insecure_default_warning(): void
    {
        $source = file_get_contents(app_path('Support/Install/Install.php'));
        $this->assertStringContainsString('warnOnInsecureDefaults', $source, 'Installer must have a method to warn on insecure defaults');
        $this->assertStringContainsString('SECURITY WARNING', $source, 'Installer must log SECURITY WARNING for insecure defaults');
        $this->assertStringContainsString('ChangeMeToYourDBPassword', $source, 'Installer must detect the DB_PASSWORD placeholder');
    }

    /**
     * The installer sets chmod 0640 on .env (from Step 6).
     */
    public function test_installer_sets_chmod_0640(): void
    {
        $source = file_get_contents(app_path('Support/Install/Install.php'));
        $this->assertStringContainsString('0640', $source, 'Installer must set 0640 permissions on .env');
    }

    /**
     * CI workflows set DB_PASSWORD explicitly (not relying on .env.example default).
     */
    public function test_ci_sets_db_password_explicitly(): void
    {
        $ci = file_get_contents(base_path('.github/workflows/ci.yml'));
        $this->assertStringContainsString('s/^DB_PASSWORD=.*/DB_PASSWORD=nexusphp/', $ci, 'CI must set DB_PASSWORD explicitly via sed');
    }
}
