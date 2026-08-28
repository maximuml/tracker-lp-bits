<?php

declare(strict_types=1);

namespace App\Console\Commands\Upgrade;

use App\Support\Database;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateTorrentsTableTextColumn extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upgrade:migrate_torrents_table_text_column';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate torrents table text column';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (Schema::hasTable('torrent_extras') && Schema::hasColumn('torrents', 'descr')) {
            DB::statement('insert into torrent_extras (torrent_id, descr, media_info, nfo, created_at) select id, descr, technical_info, nfo, now() from torrents '.Database::upsertField(['torrent_id'], ['torrent_id']));
        }
        $columns = ['ori_descr', 'descr', 'nfo', 'technical_info'];
        $sql = 'alter table torrents ';
        $drops = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn('torrents', $column)) {
                $drops[] = "drop column $column";
            }
        }
        if (! empty($drops)) {
            $sql .= implode(',', $drops);
            DB::statement($sql);
        }
    }
}
