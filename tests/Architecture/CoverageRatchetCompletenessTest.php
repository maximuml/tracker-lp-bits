<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Console\Commands\CoverageRatchetCommand;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet: каждый первоуровневый каталог `app/` обязан иметь запись в
 * `CoverageRatchetCommand::MODULE_THRESHOLDS`.
 *
 * Команда сопоставляет файлы с модулями через MODULE_THRESHOLDS —
 * каталог без записи просто не попадает в отчёт и не имеет floor'а.
 * Новый `app/Foo` иначе навсегда остался бы невидимым для coverage-
 * ratchet'а — тот же класс дыры, что вылечен шагом 0.5 для PHPStan
 * (там `app/Enums` и ещё 4 каталога годами жили вне level 8).
 *
 * Порог 0.0 допустим и означает «модуль считается и виден в отчёте,
 * floor поставит следующий bump .coverage-baseline.json».
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class CoverageRatchetCompletenessTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    private const BASELINE = __DIR__.'/../../.coverage-baseline.json';

    public function test_every_first_level_app_directory_has_a_threshold(): void
    {
        $thresholds = self::moduleThresholds();
        $missing = [];

        foreach (self::appDirectories() as $dir) {
            // Запись второго уровня (например app/Http/Controllers)
            // покрывает только своё поддерево — для полноты нужна
            // запись первого уровня или сам каталог в качестве ключа.
            if (! isset($thresholds[$dir])) {
                $missing[] = $dir;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Каталоги app/ без записи в MODULE_THRESHOLDS — coverage:ratchet их\n"
           ."вообще не считает. Добавь порог (0.0 = «считать, floor позже») в\n"
           .'CoverageRatchetCommand::MODULE_THRESHOLDS: '.implode(', ', $missing),
        );
    }

    public function test_baseline_keys_match_threshold_keys(): void
    {
        $baseline = json_decode((string) file_get_contents(self::BASELINE), true);
        $this->assertIsArray($baseline, '.coverage-baseline.json не JSON-объект');

        $orphans = array_diff(array_keys($baseline), array_keys(self::moduleThresholds()));

        $this->assertSame(
            [],
            array_values($orphans),
            'В .coverage-baseline.json модули без порога в MODULE_THRESHOLDS — '
           .'лишняя запись мёртвая, либо порог удалили по ошибке: '
           .implode(', ', $orphans),
        );
    }

    /**
     * @return array<string, float>
     */
    private static function moduleThresholds(): array
    {
        $constant = new \ReflectionClassConstant(CoverageRatchetCommand::class, 'MODULE_THRESHOLDS');

        /** @var array<string, float> */
        return $constant->getValue();
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
}
