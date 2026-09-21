<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tag::DEFAULTS shipped CJK names from upstream NexusPHP. The constants are
 * now English; rename rows that still carry the original seeded names so
 * existing installs match fresh seeds. Renamed or custom tags are untouched.
 */
return new class extends Migration
{
    /** @var array<int, array{string, string}> */
    private const RENAMES = [
        1 => ['禁转', 'No Repost'],
        2 => ['首发', 'First Release'],
        3 => ['官方', 'Official'],
        5 => ['国语', 'Mandarin Audio'],
        6 => ['中字', 'Chinese Subtitles'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $id => [$from, $to]) {
            DB::table('tags')->where('id', $id)->where('name', $from)->update(['name' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $id => [$from, $to]) {
            DB::table('tags')->where('id', $id)->where('name', $to)->update(['name' => $from]);
        }
    }
};
