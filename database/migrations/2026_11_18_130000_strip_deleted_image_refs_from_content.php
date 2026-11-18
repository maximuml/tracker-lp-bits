<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The rank GIF icons (pic/{peasant..banned}.gif), the legacy progress
 * bar and nexus logo are deleted with this change — nothing in code or
 * seeded content references them anymore. Old posts/messages/comments/
 * signatures can still hotlink them inside [img] BBCode tags; strip
 * those tags so deleted files don't show up as broken images.
 * Irreversible: the replaced text is gone.
 */
return new class extends Migration
{
    private const BASENAMES = 'peasant|user|power|elite|crazy|insane|veteran|extreme|ultimate|nexus|vip|uploader|moderator|administrator|forummoderator|sysop|retiree|staffleader|banned|progressbar';

    /** @var array<string, list<string>> table => columns holding BBCode text */
    private const TARGETS = [
        'posts' => ['body', 'ori_body'],
        'comments' => ['text', 'ori_text'],
        'messages' => ['msg'],
        'users' => ['signature'],
        'shoutbox' => ['text'],
    ];

    public function up(): void
    {
        $names = self::BASENAMES;
        $patterns = [
            // [img]url[/img] and [img width=..]url[/img]
            "\\[img[^\\]]*\\][^\\[]*pic/(?:{$names})\\.gif\\[/img\\]",
            // [img=url] self-closing form
            "\\[img=[^\\]]*pic/(?:{$names})\\.gif[^\\]]*\\]",
        ];

        foreach (self::TARGETS as $table => $columns) {
            foreach ($columns as $column) {
                foreach ($patterns as $pattern) {
                    DB::statement(
                        "UPDATE `{$table}` SET `{$column}` = REGEXP_REPLACE(`{$column}`, ?, '', 1, 0, 'i') WHERE `{$column}` REGEXP ?",
                        [$pattern, "pic/(?:{$names})\\.gif"],
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // Content removed by up() cannot be restored.
    }
};
