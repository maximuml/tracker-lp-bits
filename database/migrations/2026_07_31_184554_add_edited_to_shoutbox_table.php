<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEditedToShoutboxTable extends Migration
{
    public function up()
    {
        Schema::table('shoutbox', function (Blueprint $table) {
            if (! Schema::hasColumn('shoutbox', 'edited_by')) {
                $table->unsignedInteger('edited_by')->default(0)->after('date');
            }
            if (! Schema::hasColumn('shoutbox', 'edited_at')) {
                $table->unsignedInteger('edited_at')->default(0)->after('edited_by');
            }
        });
    }

    public function down()
    {
        Schema::table('shoutbox', function (Blueprint $table) {
            if (Schema::hasColumn('shoutbox', 'edited_by')) {
                $table->dropColumn('edited_by');
            }
            if (Schema::hasColumn('shoutbox', 'edited_at')) {
                $table->dropColumn('edited_at');
            }
        });
    }
}
