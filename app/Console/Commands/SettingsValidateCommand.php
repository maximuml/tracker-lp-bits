<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Settings;
use App\Support\SettingsSchemaValidator;
use Illuminate\Console\Command;

/**
 * Validate settings stored in the database against JSON schemas.
 *
 * Reads schemas from {@code config/settings-schema/*.json} and validates
 * every settings prefix against its corresponding schema. Reports any
 * validation errors and exits with a non-zero status if any are found.
 */
class SettingsValidateCommand extends Command
{
    protected $signature = 'settings:validate
                            {--prefix= : Only validate the given settings prefix}
                            {--fix : Coerce string values to schema-compatible types where possible}';

    protected $description = 'Validate settings against JSON schemas in config/settings-schema/';

    public function handle(SettingsSchemaValidator $validator): int
    {
        $prefixFilter = $this->option('prefix');
        $fix = (bool) $this->option('fix');

        if ($prefixFilter !== null) {
            return $this->validatePrefix($validator, $prefixFilter, $fix);
        }

        return $this->validateAll($validator, $fix);
    }

    private function validatePrefix(SettingsSchemaValidator $validator, string $prefix, bool $fix): int
    {
        if (! $validator->hasSchema($prefix)) {
            $this->error("No schema found for prefix '{$prefix}'.");

            return self::FAILURE;
        }

        $allSettings = Settings::fromDb();
        $values = $allSettings[$prefix] ?? [];
        if (! is_array($values)) {
            $this->warn("Prefix '{$prefix}' has no values in the database.");

            return self::SUCCESS;
        }

        $errors = $validator->validatePrefix($prefix, $values);
        if (empty($errors)) {
            $this->info("Prefix '{$prefix}': valid (".count($values).' keys)');

            return self::SUCCESS;
        }

        $this->error("Prefix '{$prefix}': ".count($errors).' validation error(s)');
        foreach ($errors as $error) {
            $this->line("  - {$error}");
        }

        return self::FAILURE;
    }

    private function validateAll(SettingsSchemaValidator $validator, bool $fix): int
    {
        $results = $validator->validateAll();
        $schemas = $validator->schemas();

        $this->info('Validating settings against '.count($schemas).' schema(s)...');

        if (empty($results)) {
            $this->info('All settings are valid.');

            return self::SUCCESS;
        }

        $totalErrors = 0;
        foreach ($results as $prefix => $errors) {
            $totalErrors += count($errors);
            $this->error("Prefix '{$prefix}': ".count($errors).' validation error(s)');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }

        $this->error("Total: {$totalErrors} validation error(s) across ".count($results).' prefix(es)');

        return self::FAILURE;
    }
}
