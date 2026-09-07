<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W3-06: Create archive tables for iplog and login_logs.
 *
 * MySQL native partitioning does not support foreign keys, which both
 * tables have (userid/uid → users.id ON DELETE CASCADE). Instead of
 * dropping FKs, we use archival tables: the PruneActivityLogJob moves
 * records older than the retention threshold into *_archive tables
 * before deleting them from the source tables.
 *
 * This provides the same benefit as monthly partitioning — old data
 * is separated from the hot table — without breaking referential
 * integrity.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iplog_archive')) {
            Schema::create('iplog_archive', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('ip', 64)->default('');
                $table->unsignedBigInteger('userid')->default(0);
                $table->dateTime('access')->nullable();
                $table->string('uri', 255)->nullable();
                $table->integer('count')->default(0);
                $table->timestamp('archived_at')->useCurrent();

                $table->index('userid');
                $table->index('access');
                $table->index('ip');
            });
        }

        if (! Schema::hasTable('login_logs_archive')) {
            Schema::create('login_logs_archive', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('uid')->default(0);
                $table->string('ip', 128);
                $table->string('country', 255)->nullable();
                $table->string('city', 255)->nullable();
                $table->string('client', 255)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('archived_at')->useCurrent();

                $table->index('uid');
                $table->index('ip');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iplog_archive');
        Schema::dropIfExists('login_logs_archive');
    }
};
