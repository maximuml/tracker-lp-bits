<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Report submission ran reportExists() then insert() with no backing
     * constraint — concurrent reports from the same user duplicated rows.
     * Dedupe existing rows (earliest wins) and enforce
     * (addedby, reportid, type) uniqueness at the engine level.
     */
    public function up(): void
    {
        DB::statement(
            'DELETE r2 FROM reports r1 JOIN reports r2 ON r2.addedby = r1.addedby AND r2.reportid = r1.reportid AND r2.type = r1.type AND r2.id > r1.id'
        );
        Schema::table('reports', function (Blueprint $table) {
            $table->unique(['addedby', 'reportid', 'type'], 'reports_addedby_reportid_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique('reports_addedby_reportid_type_unique');
        });
    }
};
