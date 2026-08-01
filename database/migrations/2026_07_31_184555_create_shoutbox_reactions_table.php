<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShoutboxReactionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('shoutbox_reactions')) {
            return;
        }
        Schema::create('shoutbox_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('shoutbox_id');
            $table->unsignedInteger('user_id');
            $table->string('reaction', 16)->charset('utf8mb4')->collation('utf8mb4_bin');
            $table->timestamp('created_at')->nullable();

            $table->unique(['shoutbox_id', 'user_id', 'reaction'], 'shoutbox_reactions_unique');
            $table->index('shoutbox_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shoutbox_reactions');
    }
}
