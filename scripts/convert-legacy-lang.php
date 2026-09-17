<?php

declare(strict_types=1);

/**
 * One-shot converter: lang/en/lang_<x>.php ($lang_x = [...])
 *   -> resources/lang/en/legacy/<x>.php (return [...]).
 *
 * File names are derived from the VARIABLE name (not the file name):
 * lang_bitbucket-upload.php defines $lang_bitbucketupload, so it becomes
 * legacy/bitbucketupload.php and is read via trans('legacy/bitbucketupload').
 *
 * Usage: php scripts/convert-legacy-lang.php
 */
$srcDir = __DIR__.'/../lang/en';
$dstDir = __DIR__.'/../resources/lang/en/legacy';

if (! is_dir($dstDir) && ! mkdir($dstDir, 0755, true)) {
    fwrite(STDERR, "cannot create $dstDir\n");
    exit(1);
}

$converted = 0;
foreach (glob("$srcDir/lang_*.php") ?: [] as $src) {
    $text = file_get_contents($src);
    if ($text === false) {
        fwrite(STDERR, "cannot read $src\n");
        exit(1);
    }

    if (! preg_match('/\$lang_([a-zA-Z0-9_]+)\s*=/', $text, $m)) {
        fwrite(STDERR, "no \$lang_* assignment in $src\n");
        exit(1);
    }
    $suffix = $m[1];

    $new = preg_replace('/\$lang_[a-zA-Z0-9_]+\s*=\s*/', 'return ', $text, 1);
    if ($new === null || $new === $text) {
        fwrite(STDERR, "conversion failed in $src\n");
        exit(1);
    }

    $dst = "$dstDir/$suffix.php";
    if (file_put_contents($dst, $new) === false) {
        fwrite(STDERR, "cannot write $dst\n");
        exit(1);
    }
    $converted++;
    echo basename($src)." => legacy/$suffix.php\n";
}

echo "converted $converted files\n";
