<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nexus\Database\NexusDB;

return new class extends Migration
{
    private static array $tables = [
        'sources', 'media', 'standards', 'codecs', 'audiocodecs', 'processings', 'secondicons',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (self::$tables as $table) {
            if (! NexusDB::hasColumn($table, 'mode')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->integer('mode')->default(0);
                });
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
        foreach (self::$tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('mode');
            });
        }
    }
};
