<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legacy NexusPHP seed data stored raw markup in rules.title
 * (`<font class=striking>…</font>` plus pre-encoded `&amp;`). The Blade
 * views render the title with {{ }} escaping, so the markup was leaking
 * as literal text on /rules and /modrules. Strip tags and decode
 * entities so titles are plain text.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('rules')->orderBy('id')->get(['id', 'title'])->each(function ($rule): void {
            $clean = html_entity_decode(strip_tags((string) $rule->title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($clean !== $rule->title) {
                DB::table('rules')->where('id', $rule->id)->update(['title' => $clean]);
            }
        });
    }

    public function down(): void
    {
        // Data cleanup is one-way.
    }
};
