<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class DropAllowedemailsAndBannedemailsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('allowedemails');
        Schema::dropIfExists('bannedemails');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Tables are intentionally not recreated; the email-domain
        // restriction feature has been removed.
    }
}
