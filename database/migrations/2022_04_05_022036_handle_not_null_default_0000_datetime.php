<?php

use App\Repositories\UpgradeRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (NexusDB::isPgsql()) {
            // fresh install no need
            return;
        }
        $tableFields = UpgradeRepository::DATETIME_INVALID_VALUE_FIELDS;

        foreach ($tableFields as $table => $fields) {
            $columnInfo = NexusDB::getMysqlColumnInfo($table);
            $modifies = [];
            foreach ($fields as $field) {
                if (isset($columnInfo[$field]) && $columnInfo[$field]['COLUMN_DEFAULT'] == '0000-00-00 00:00:00') {
                    $modifies[] = sprintf('modify `%s` datetime default null', $field);
                }
            }
            if (! empty($modifies)) {
                $sql = sprintf('alter table `%s` %s', $table, implode(', ', $modifies));
                DB::statement($sql);
            }
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
