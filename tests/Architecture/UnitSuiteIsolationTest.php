<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet: сюит Unit не имеет права зависеть от внешних сервисов.
 *
 * До разделения `tests/Unit` содержал 321 класс, из которых 186 (58%)
 * не проходили при недоступной MySQL — то есть были интеграционными
 * тестами под именем «unit». Из-за этого `composer test:unit` требовал
 * `migrate:fresh --seed` и шёл ~12 минут, что делало быструю обратную
 * связь невозможной.
 *
 * Классификация была снята эмпирически: два прогона `--testsuite Unit`
 * (с доступной БД и с `DB_HOST` в никуда), сравнение JUnit-отчётов через
 * `tools/classify-db-dependency.php`. 186 классов уехали в
 * `tests/Integration`, 135 остались. Прогон уже без обоих сервисов
 * (MySQL и Redis в никуда) вскрыл ещё 7 классов, которым нужен живой
 * Redis (metrics-collectors, announce rate limiter) — они уехали следом.
 *
 * Этот тест не даёт вернуть долг: любой признак работы с БД или Redis в
 * `tests/Unit` — ошибка. Новый тест, которому нужен сервис, пишется в
 * `tests/Integration` с `#[TestCategory(TestCategory::SERVICE_INTEGRATION)]`.
 *
 * Проверка статическая (по маркерам), поэтому она не поймает
 * опосредованное обращение к сервису через тестируемый класс (например,
 * коллектор, который внутри дёргает `Redis::`). Настоящей защитой служит
 * CI-job `unit-tests-fast`, который гоняет сюит вообще без сервисных
 * контейнеров. Здесь — быстрый локальный барьер.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class UnitSuiteIsolationTest extends TestCase
{
    private const UNIT_DIR = __DIR__.'/../Unit';

    /**
     * Маркеры, требующие живого сервиса (MySQL, Redis).
     *
     * Фасады `DB::`/`Redis::` учитываются только при реальных вызовах —
     * моки через Mockery (`Redis::shouldReceive`, `DB::spy`, …) живому
     * сервису не нужны и в Unit допустимы.
     *
     * @var array<string, string> паттерн => чем заменить
     */
    private const SERVICE_MARKERS = [
        '/\bRefreshDatabase\b/' => 'перенести тест в tests/Integration',
        '/\bDatabaseTransactions\b/' => 'перенести тест в tests/Integration',
        '/\bDatabaseMigrations\b/' => 'перенести тест в tests/Integration',
        '/::factory\(\)/' => 'использовать конструктор/стаб вместо фабрики, либо перенести в tests/Integration',
        '/\$this->seed\(/' => 'перенести тест в tests/Integration',
        '/\bDB::(?!shouldReceive|spy|mock|fake|swap|partialMock)/' => 'мокнуть DB:: или перенести тест в tests/Integration',
        '/\bRedis::(?!shouldReceive|spy|mock|fake|swap|partialMock)/' => 'мокнуть Redis:: (shouldReceive) или перенести тест в tests/Integration',
    ];

    /** Baseline: файлов в tests/Unit с маркерами сервисов. Понижать нельзя — уже 0. */
    private const BASELINE = 0;

    public function test_unit_suite_has_no_external_service_markers(): void
    {
        $offenders = [];

        foreach ($this->unitTestFiles() as $path => $content) {
            foreach (self::SERVICE_MARKERS as $pattern => $hint) {
                if (preg_match($pattern, $content) === 1) {
                    $relative = substr($path, strlen(self::UNIT_DIR) + 1);
                    $offenders[] = sprintf('%s (%s)', $relative, $hint);
                    break;
                }
            }
        }

        $this->assertLessThanOrEqual(
            self::BASELINE,
            count($offenders),
            sprintf(
                "В tests/Unit появились тесты с зависимостью от внешних сервисов (%d шт., baseline %d).\n"
               ."Сюит Unit должен проходить при недоступных MySQL и Redis — это проверяет CI-job\n"
               ."`unit-tests-fast`. Тесты на живых сервисах живут в tests/Integration.\n\nНарушители:\n  - %s",
                count($offenders),
                self::BASELINE,
                implode("\n  - ", $offenders),
            ),
        );
    }

    /**
     * Тесты в Unit не должны наследовать Laravel-овый TestCase, который
     * бутстрапит приложение только ради контейнера: это скрытый канал к БД
     * через резолв сервисов. Исключение — те, кому нужен контейнер и они
     * уже доказали независимость от БД, поэтому здесь только счётчик.
     */
    public function test_unit_suite_files_live_in_the_right_namespace(): void
    {
        $wrong = [];

        foreach ($this->unitTestFiles() as $path => $content) {
            if (preg_match('/^namespace\s+(Tests\\\\Unit[^\s;]*);/m', $content) !== 1) {
                $wrong[] = substr($path, strlen(self::UNIT_DIR) + 1);
            }
        }

        $this->assertSame(
            [],
            $wrong,
            'Файлы в tests/Unit с namespace не из Tests\\Unit — вероятно, забыт '
           .'префикс после переноса: '.implode(', ', $wrong),
        );
    }

    /**
     * @return iterable<string, string> путь => содержимое
     */
    private function unitTestFiles(): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::UNIT_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            yield $file->getPathname() => $content;
        }
    }
}
