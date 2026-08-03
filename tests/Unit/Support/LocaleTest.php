<?php

namespace Tests\Unit\Support;

use App\Support\Locale;
use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase
{
    public function test_file_path_uses_language_folder_by_default(): void
    {
        $this->assertSame('lang/en/lang_functions.php', Locale::filePath('en', 'functions.php'));
    }

    public function test_file_path_uses_target_folder_when_target_requested(): void
    {
        $this->assertSame('lang/_target/lang_functions.php', Locale::filePath('en', 'functions.php', '', true));
    }

    public function test_file_path_infers_script_name_from_server_script(): void
    {
        $this->assertSame('lang/en/lang_index.php', Locale::filePath('en', '', '/public/index.php'));
    }

    public function test_file_path_prefers_explicit_script_name(): void
    {
        $this->assertSame('lang/en/lang_details.php', Locale::filePath('en', 'details.php', '/public/index.php'));
    }

    public function test_file_path_strips_directory_but_keeps_extension(): void
    {
        $this->assertSame('lang/en/lang_test.php', Locale::filePath('en', '', '/some/path/test.php'));
    }

    public function test_file_path_with_empty_server_script_returns_empty_name(): void
    {
        $this->assertSame('lang/en/lang_', Locale::filePath('en', '', ''));
    }
}
