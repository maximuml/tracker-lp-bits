<?php

use App\Repositories\UpgradeRepository;
use App\Support\Database;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Database::isPgsql()) {
            // fresh install no need
            return;
        }
        $tableFields = UpgradeRepository::DATETIME_INVALID_VALUE_FIELDS;

        foreach ($tableFields as $table => $fields) {
            $columnInfo = Database::getMysqlColumnInfo($table);
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
