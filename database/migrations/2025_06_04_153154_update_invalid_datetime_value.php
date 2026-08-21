<?php

use App\Repositories\UpgradeRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (NexusDB::isPgsql()) {
            return;
        }
        $tableFields = UpgradeRepository::DATETIME_INVALID_VALUE_FIELDS;

        foreach ($tableFields as $table => $fields) {
            $columnInfo = NexusDB::getMysqlColumnInfo($table);
            foreach ($fields as $field) {
                if (isset($columnInfo[$field]) && $columnInfo[$field]['DATA_TYPE'] == 'datetime') {
                    DB::statement("update $table set $field = null where $field = '0000-00-00 00:00:00'");
                }
            }
        }
        $columnInfo = NexusDB::getMysqlColumnInfo('snatched');
        if (isset($columnInfo['finish_ip'])) {
            DB::statement('alter table snatched drop column finish_ip');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
