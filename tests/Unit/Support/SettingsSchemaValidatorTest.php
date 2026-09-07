<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Exceptions\SettingsValidationException;
use App\Repositories\SettingRepository;
use App\Support\Settings;
use App\Support\SettingsSchemaValidator;
use Tests\TestCase;

class SettingsSchemaValidatorTest extends TestCase
{
    private SettingsSchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(SettingsSchemaValidator::class);
    }

    public function test_schemas_are_loaded_from_config_directory(): void
    {
        $schemas = $this->validator->schemas();

        $this->assertArrayHasKey('main', $schemas);
        $this->assertArrayHasKey('basic', $schemas);
        $this->assertArrayHasKey('smtp', $schemas);
        $this->assertSame('object', $schemas['main']['type']);
    }

    public function test_has_schema_returns_true_for_known_prefixes(): void
    {
        $this->assertTrue($this->validator->hasSchema('main'));
        $this->assertTrue($this->validator->hasSchema('basic'));
    }

    public function test_has_schema_returns_false_for_unknown_prefix(): void
    {
        $this->assertFalse($this->validator->hasSchema('nonexistent_prefix'));
    }

    public function test_validate_prefix_returns_empty_for_valid_values(): void
    {
        $errors = $this->validator->validatePrefix('basic', [
            'SITENAME' => 'My Tracker',
            'BASEURL' => 'https://example.com',
            'announce_url' => 'https://example.com/announce',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_validate_prefix_returns_errors_for_invalid_type(): void
    {
        // basic.SITENAME expects string; pass an array
        $errors = $this->validator->validatePrefix('basic', [
            'SITENAME' => [1, 2, 3],
        ]);

        $this->assertNotEmpty($errors);
    }

    public function test_validate_prefix_returns_empty_for_unknown_prefix(): void
    {
        $errors = $this->validator->validatePrefix('nonexistent_prefix', [
            'foo' => 'bar',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_validate_all_returns_empty_when_settings_are_valid(): void
    {
        // The seeded settings should validate against the schemas.
        $results = $this->validator->validateAll();

        $this->assertSame([], $results);
    }

    public function test_settings_validate_command_exits_zero_for_valid_settings(): void
    {
        $this->artisan('settings:validate')
            ->expectsOutput('All settings are valid.')
            ->assertSuccessful();
    }

    public function test_settings_validate_command_with_prefix_filter(): void
    {
        $this->artisan('settings:validate', ['--prefix' => 'basic'])
            ->expectsOutputToContain('valid')
            ->assertSuccessful();
    }

    public function test_settings_validate_command_fails_for_unknown_prefix(): void
    {
        $this->artisan('settings:validate', ['--prefix' => 'nonexistent'])
            ->expectsOutputToContain('No schema found')
            ->assertFailed();
    }

    public function test_save_batch_throws_validation_exception_for_invalid_values(): void
    {
        $this->expectException(SettingsValidationException::class);

        // basic.SITENAME expects string; pass an array
        app(SettingRepository::class)->saveBatch('basic', [
            'SITENAME' => [1, 2, 3],
        ]);
    }

    public function test_save_batch_succeeds_for_valid_values(): void
    {
        app(SettingRepository::class)->saveBatch('basic', [
            'SITENAME' => 'Test Tracker',
        ]);

        // Verify it was saved
        Settings::resetCache();
        $this->assertSame('Test Tracker', Settings::get('basic.SITENAME'));
    }
}
