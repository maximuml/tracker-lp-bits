<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_cursors', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->string('channel', 32);
            $table->unsignedBigInteger('last_id')->default(0);
            $table->primary(['user_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_cursors');
    }
};
