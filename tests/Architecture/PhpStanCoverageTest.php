<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet: `parameters.paths` в phpstan.neon обязан покрывать каждый
 * первоуровневый подкаталог `app/`.
 *
 * До шага 0.5 в paths отсутствовали `app/Enums`, `app/Contracts`,
 * `app/ViewModels`, `app/View` и `app/Observers` — ~7300 LOC жили вне
 * level 8 при заявленном в AGENTS.md «all code is strictly typed».
 * Каталоги выпали молча: при добавлении нового `app/Foo` ничто не
 * заставляло внести его в конфиг.
 *
 * Тест перечисляет реальные каталоги `app/*` и сверяет с фактическим
 * содержимым phpstan.neon: новый каталог без записи в paths — падение.
 * Исключения наоборот должны быть явными: любой элемент
 * `excludePaths.analyse`, вырезающий код из `app/`, обязан быть в
 * {@see self::ALLOWED_EXCLUSIONS} с обоснованием — иначе «дыру» можно
 * открыть, не трогая paths вовсе.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class PhpStanCoverageTest extends TestCase
{
    private const CONFIG = __DIR__.'/../../phpstan.neon';

    private const APP_DIR = __DIR__.'/../../app';

    /**
     * Единственные разрешённые исключения из анализа под app/.
     * Порядок и значения должны совпадать с phpstan.neon.
     *
     * @var list<string>
     */
    private const ALLOWED_EXCLUSIONS = [
        // Шаблоны-фрагменты legacy-слоёв — не самостоятельные файлы.
        'app/Services/Legacy/*_content.php',
        'app/Services/Legacy/partials (?)',
    ];

    public function test_every_first_level_app_directory_is_analysed(): void
    {
        $paths = self::configuredPaths();
        $this->assertNotEmpty($paths, 'Не удалось прочитать parameters.paths из phpstan.neon');

        $uncovered = [];
        foreach (self::appDirectories() as $dir) {
            foreach ($paths as $path) {
                $path = rtrim($path, '/');
                if ($dir === $path || str_starts_with($dir, $path.'/')) {
                    continue 2;
                }
            }
            $uncovered[] = $dir;
        }

        $this->assertSame(
            [],
            $uncovered,
            "Каталоги app/ вне parameters.paths — PHPStan их не видит.\n"
           ."Добавь каталог в phpstan.neon paths или (с обоснованием) в excludePaths\n"
           ."и в self::ALLOWED_EXCLUSIONS этого теста:\n  - ".implode("\n  - ", $uncovered),
        );
    }

    public function test_exclusions_are_limited_to_the_allowlist(): void
    {
        $excluded = self::excludedPaths();
        $this->assertNotEmpty($excluded, 'Не удалось прочитать parameters.excludePaths.analyse из phpstan.neon');

        sort($excluded);
        $allowed = self::ALLOWED_EXCLUSIONS;
        sort($allowed);

        $this->assertSame(
            $allowed,
            $excluded,
            "parameters.excludePaths.analyse отличается от разрешённого списка.\n"
           ."Новое исключение должно быть обосновано в self::ALLOWED_EXCLUSIONS;\n"
           .'удалённое исключение — вычеркнуто оттуда же.',
        );
    }

    public function test_configured_app_paths_exist(): void
    {
        $missing = [];
        foreach (self::configuredPaths() as $path) {
            if (str_contains($path, '*') || ! str_starts_with($path, 'app/')) {
                continue;
            }
            if (! is_dir(self::APP_DIR.'/../'.ltrim($path, '/'))) {
                $missing[] = $path;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'parameters.paths содержит несуществующие каталоги — опечатка в '
           .'paths молча выключает анализ: '.implode(', ', $missing),
        );
    }

    /**
     * @return list<string> первоуровневые подкаталоги app/ как `app/<Name>`
     */
    private static function appDirectories(): array
    {
        $dirs = [];
        foreach (glob(self::APP_DIR.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $dirs[] = 'app/'.basename($dir);
        }
        sort($dirs);

        return $dirs;
    }

    /**
     * @return list<string> элементы `parameters.paths` из phpstan.neon
     */
    private static function configuredPaths(): array
    {
        return self::items(self::indentedBlock(self::config(), 'paths', 4));
    }

    /**
     * @return list<string> элементы `parameters.excludePaths.analyse`
     */
    private static function excludedPaths(): array
    {
        $exclude = self::indentedBlock(self::config(), 'excludePaths', 4);

        return self::items(self::indentedBlock(implode("\n", $exclude), 'analyse', 8));
    }

    private static function config(): string
    {
        $contents = file_get_contents(self::CONFIG);
        self::assertIsString($contents);

        return $contents;
    }

    /**
     * NEON-парсера в рантайме тестов нет, поэтому разбор по отступам —
     * формат секций конфига стабилен.
     *
     * @param  list<string>  $block
     * @return list<string>
     */
    private static function items(array $block): array
    {
        $items = [];
        foreach ($block as $line) {
            if (preg_match('/^\s*-\s+(.+?)\s*$/', $line, $m) === 1 && ! str_starts_with($m[1], '#')) {
                $items[] = $m[1];
            }
        }

        return $items;
    }

    /**
     * @return list<string> строки блока после `key:` с отступом больше $indent
     */
    private static function indentedBlock(string $contents, string $key, int $indent): array
    {
        $lines = explode("\n", $contents);
        $block = [];
        $inside = false;

        foreach ($lines as $line) {
            if (! $inside) {
                if ($line === str_repeat(' ', $indent).$key.':') {
                    $inside = true;
                }

                continue;
            }

            if (trim($line) === '') {
                $block[] = $line;

                continue;
            }

            if (strlen($line) - strlen(ltrim($line)) <= $indent) {
                break;
            }

            $block[] = $line;
        }

        return $block;
    }
}
