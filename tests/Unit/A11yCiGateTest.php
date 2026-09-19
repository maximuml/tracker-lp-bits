<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * A11y CI gate — formerly asserted the standalone a11y.yml axe-CLI
 * workflow; that workflow was replaced by the Playwright a11y.spec
 * inside the blocking browser-smoke job (ADR 0016).
 *
 * Verifies that:
 * - ci.yml has a browser-smoke job that runs the Playwright suite
 * - a11y.spec.ts exists, scans pages with axe and WCAG 2.x tags
 * - a11y-baseline.json ratchets per-page rule-ids for public and
 *   authenticated pages
 * - the job uploads artifacts on failure and uses pinned actions
 * - a local .axerc.json config exists for developer use
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class A11yCiGateTest extends TestCase
{
    private string $ciPath;

    private string $specPath;

    private string $baselinePath;

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ciPath = base_path('.github/workflows/ci.yml');
        $this->specPath = base_path('tests/browser/specs/a11y.spec.ts');
        $this->baselinePath = base_path('tests/browser/a11y-baseline.json');
        $this->configPath = base_path('.axerc.json');
    }

    /**
     * The browser-smoke job exists in ci.yml and runs the suite.
     */
    public function test_browser_smoke_job_runs_playwright(): void
    {
        $content = file_get_contents($this->ciPath);
        $this->assertStringContainsString('browser-smoke:', $content, 'browser-smoke job must exist');
        $this->assertStringContainsString('npx playwright test', $content, 'job must run playwright');
        $this->assertStringContainsString('tests/browser', $content, 'job must run tests/browser');
    }

    /**
     * The job uploads artifacts (report + traces) on failure.
     */
    public function test_browser_smoke_uploads_artifacts(): void
    {
        $content = file_get_contents($this->ciPath);
        $this->assertStringContainsString('upload-artifact', $content);
        $this->assertStringContainsString('playwright-report', $content);
        $this->assertStringContainsString('test-results', $content);
    }

    /**
     * The a11y spec exists and drives axe-core with WCAG 2.x tags.
     */
    public function test_a11y_spec_scans_with_wcag_tags(): void
    {
        $this->assertFileExists($this->specPath);
        $content = file_get_contents($this->specPath);
        $this->assertStringContainsString('AxeBuilder', $content);
        $this->assertStringContainsString('wcag2a', $content);
        $this->assertStringContainsString('wcag2aa', $content);
        $this->assertStringContainsString('wcag21a', $content);
        $this->assertStringContainsString('wcag21aa', $content);
    }

    /**
     * The spec scans authenticated pages too — a11y.yml only had public ones.
     */
    public function test_a11y_spec_covers_authenticated_pages(): void
    {
        $content = file_get_contents($this->specPath);
        $this->assertStringContainsString('authenticated', $content);
        $this->assertStringContainsString('/usercp.php', $content);
        $this->assertStringContainsString('/messages.php', $content);
    }

    /**
     * The baseline ratchets per-page rule-ids for both page groups.
     */
    public function test_a11y_baseline_structure(): void
    {
        $this->assertFileExists($this->baselinePath);
        $baseline = json_decode(file_get_contents($this->baselinePath), true);
        $this->assertIsArray($baseline);
        $this->assertArrayHasKey('public', $baseline);
        $this->assertArrayHasKey('authenticated', $baseline);
        $this->assertNotEmpty($baseline['public']);
        $this->assertNotEmpty($baseline['authenticated']);
    }

    /**
     * The workflow uses pinned action SHAs (not floating tags).
     */
    public function test_workflow_uses_pinned_actions(): void
    {
        $content = file_get_contents($this->ciPath);
        $this->assertMatchesRegularExpression(
            '/actions\/checkout@[0-9a-f]{40}/',
            $content,
            'actions/checkout must be pinned to a SHA'
        );
    }

    /**
     * A local .axerc.json config exists for developer use.
     */
    public function test_axerc_config_exists(): void
    {
        $this->assertFileExists($this->configPath, '.axerc.json must exist for local a11y testing');
    }

    /**
     * The .axerc.json config includes WCAG 2.1 tags.
     */
    public function test_axerc_config_includes_wcag21(): void
    {
        $config = json_decode(file_get_contents($this->configPath), true);
        $this->assertIsArray($config);
        $this->assertContains('wcag21a', $config['tags']);
        $this->assertContains('wcag21aa', $config['tags']);
    }

    /**
     * The .axerc.json config includes key accessibility rules.
     */
    public function test_axerc_config_includes_key_rules(): void
    {
        $config = json_decode(file_get_contents($this->configPath), true);
        $this->assertIsArray($config);
        $this->assertContains('color-contrast', $config['rules']);
        $this->assertContains('image-alt', $config['rules']);
        $this->assertContains('label', $config['rules']);
        $this->assertContains('button-name', $config['rules']);
        $this->assertContains('html-has-lang', $config['rules']);
    }
}
