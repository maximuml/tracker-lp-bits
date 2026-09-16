<?php

/**
 * Классифицирует тесты по зависимости от MySQL, сравнивая два JUnit-отчёта:
 * один снят с доступной БД, другой — с недоступной.
 *
 * Класс попадает в "нужна БД", если он зелёный с БД и падает без неё.
 * Класс, падающий в обоих прогонах, — предсуществующая поломка, не наша забота.
 *
 * Usage: php tools/classify-db-dependency.php with-db.xml no-db.xml
 */

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/classify-db-dependency.php <with-db.xml> <no-db.xml>\n");
    exit(1);
}

/**
 * @return array<string, bool> class name => passed
 */
function classResults(string $path): array
{
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        fwrite(STDERR, "Cannot parse $path\n");
        exit(1);
    }

    $result = [];
    foreach ($xml->xpath('//testcase') ?: [] as $case) {
        $class = (string) $case['class'];
        if ($class === '') {
            continue;
        }
        // NB: в SimpleXML обращение к отсутствующему дочернему узлу возвращает
        // пустой элемент, а не null — сравнение с null здесь не работает.
        $failed = count($case->error) > 0 || count($case->failure) > 0;
        // Класс считается зелёным, только если зелёны все его тесты.
        $result[$class] = ($result[$class] ?? true) && ! $failed;
    }

    return $result;
}

$withDb = classResults($argv[1]);
$noDb = classResults($argv[2]);

$needsDb = [];
$pureUnit = [];
$brokenBoth = [];
$missingNoDb = [];

foreach ($withDb as $class => $passedWithDb) {
    if (! array_key_exists($class, $noDb)) {
        // Класс не дошёл до выполнения без БД (фатал на bootstrap) — считаем зависимым.
        $missingNoDb[] = $class;

        continue;
    }

    if (! $passedWithDb) {
        $brokenBoth[] = $class;

        continue;
    }

    if ($noDb[$class]) {
        $pureUnit[] = $class;
    } else {
        $needsDb[] = $class;
    }
}

sort($needsDb);
sort($pureUnit);
sort($brokenBoth);
sort($missingNoDb);

$out = [
    'needs_db' => $needsDb,
    'pure_unit' => $pureUnit,
    'broken_in_both' => $brokenBoth,
    'missing_without_db' => $missingNoDb,
];

file_put_contents(
    __DIR__.'/../db-dependency.json',
    json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
);

printf("needs_db            : %d\n", count($needsDb));
printf("pure_unit           : %d\n", count($pureUnit));
printf("broken_in_both      : %d\n", count($brokenBoth));
printf("missing_without_db  : %d\n", count($missingNoDb));
printf("\nЗаписано в db-dependency.json\n");
