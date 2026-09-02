<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Wave 5 Step 27: A11y CI gate.
 *
 * Verifies that:
 * - The a11y workflow exists and scans multiple pages
 * - The workflow fails on violations (is a gate, not just informational)
 * - axe-core results are saved as artifacts
 * - WCAG 2.1 tags are included (not just WCAG 2.0)
 * - A local .axerc.json config exists for developer use
 */
final class A11yCiGateTest extends TestCase
{
    private string $workflowPath;

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflowPath = base_path('.github/workflows/a11y.yml');
        $this->configPath = base_path('.axerc.json');
    }

    /**
     * The a11y workflow file exists.
     */
    public function test_a11y_workflow_exists(): void
    {
        $this->assertFileExists($this->workflowPath, 'a11y.yml workflow must exist');
    }

    /**
     * The workflow triggers on push and pull_request to php8.
     */
    public function test_a11y_workflow_triggers_on_php8(): void
    {
        $content = file_get_contents($this->workflowPath);
        $this->assertStringContainsString('push:', $content);
        $this->assertStringContainsString('pull_request:', $content);
        $this->assertStringContainsString('php8', $content);
    }

    /**
     * The workflow has concurrency control.
     */
    public function test_a11y_workflow_has_concurrency_control(): void
    {
        $content = file_get_contents($this->workflowPath);
        $this->assertStringContainsString('concurrency:', $content);
        $this->assertStringContainsString('cancel-in-progress: true', $content);
    }

    /**
     * The workflow has minimal permissions.
     */
    public function test_a11y_workflow_has_minimal_permissions(): void
    {
        $content = file_get_contents($this->workflowPath);
        $this->assertStringContainsString('permissions:', $content);
        $this->assertStringContainsString('contents: read', $content);
    }

    /**
     * The workflow scans multiple pages (not just 3).
     */
    public function test_a11y_workflow_scans_multiple_pages(): void
    {
        $content = file_get_contents($this->workflowPath);
        $this->assertStringContainsString('/index', $content);
        $this->assertStringContainsString('/login', $content);
        $this->assertStringContainsString('/signup', $content);
        $this->assertStringContainsString('/faq', $content);
        $this->assertStringContainsString('/rules', $content);
    }

    /**
     * The workflow includes WCAG 2.1 tags (not just WCAG 2.0).
     */
    public function test_a11y_workflow_includes_wcag21_tags(): void
    {
        $content = file_get_contents($this->workflowPath);
        $this->assertStringContainsString('wcag2a', $content);
        $this->assertStringContainsString('wcag2aa', $content);
        $this->assertStringContainsString('wcag21a', $content);
        $this->assertStringContainsString('wcag21aa', $content);
    }

    /**
     * The workflow fails on violations (is a gate).
     */
    public function test_a11y_workflow_fails_on_violations(): void
    {
        $content = file_get_contents($this->workflowPath);
        $this->assertStringContainsString('exit 1', $content, 'Workflow must exit 1 on violations');
        $this->assertStringContainsString('::error::', $content, 'Workflow must report errors');
    }

    /**
     * The workflow saves axe results as JSON artifacts.
     */
    public function test_a11y_workflow_saves_artifacts(): void
    {
        $content = file_get_contents($this->workflowPath);
        $this->assertStringContainsString('upload-artifact', $content);
        $this->assertStringContainsString('axe-results', $content);
        $this->assertStringContainsString('--save', $content);
    }

    /**
     * The workflow uses pinned action SHAs (not floating tags).
     */
    public function test_a11y_workflow_uses_pinned_actions(): void
    {
        $content = file_get_contents($this->workflowPath);
        // Check that actions/checkout uses a SHA
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
