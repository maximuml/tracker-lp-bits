<?php

use App\Repositories\UpgradeRepository;
use App\Support\Database;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Database::isPgsql()) {
            return;
        }
        $tableFields = UpgradeRepository::DATETIME_INVALID_VALUE_FIELDS;

        foreach ($tableFields as $table => $fields) {
            $columnInfo = Database::getMysqlColumnInfo($table);
            foreach ($fields as $field) {
                if (isset($columnInfo[$field]) && $columnInfo[$field]['DATA_TYPE'] == 'datetime') {
                    // Use a valid lower-bound date for the comparison. The literal
                    // '0000-00-00 00:00:00' is rejected under MySQL strict mode, but
                    // any invalid/zero date in the column will still be < '1000-01-01'.
                    DB::statement("update $table set $field = null where $field < '1000-01-01 00:00:00'");
                }
            }
        }
        $columnInfo = Database::getMysqlColumnInfo('snatched');
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
