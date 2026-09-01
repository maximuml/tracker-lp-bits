<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create migrations for legacy tables that previously only existed in
 * _db/dbstructure_v1.6.sql. This eliminates schema drift between the
 * SQL dump and the migration system — migrations are now the single
 * source of truth for table structure.
 *
 * Tables created: advertisements, downloadspeed, fun, funvotes, isp,
 * links, prolinkclicks, requests, schools, subs, teams, uploadspeed.
 *
 * Seed data for downloadspeed, isp, schools, teams, uploadspeed is
 * handled by DatabaseSeeder via the installer's importInitialData().
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'advertisements' => function (Blueprint $table) {
                $table->unsignedMediumInteger('id', true);
                $table->boolean('enabled')->default(false);
                $table->enum('type', ['bbcodes', 'xhtml', 'text', 'image', 'flash']);
                $table->enum('position', ['header', 'footer', 'belownav', 'belowsearchbox', 'torrentdetail', 'comment', 'interoverforums', 'forumpost', 'popup']);
                $table->tinyInteger('displayorder')->default(0);
                $table->string('name', 255)->default('');
                $table->text('parameters');
                $table->text('code');
                $table->dateTime('starttime');
                $table->dateTime('endtime');
            },
            'downloadspeed' => function (Blueprint $table) {
                $table->unsignedTinyInteger('id', true);
                $table->string('name', 50)->default('');
            },
            'fun' => function (Blueprint $table) {
                $table->unsignedMediumInteger('id', true);
                $table->unsignedMediumInteger('userid')->default(0);
                $table->dateTime('added')->nullable();
                $table->text('body')->nullable();
                $table->string('title', 255)->default('');
                $table->enum('status', ['normal', 'dull', 'notfunny', 'funny', 'veryfunny', 'banned'])->default('normal');
            },
            'funvotes' => function (Blueprint $table) {
                $table->unsignedMediumInteger('funid');
                $table->unsignedMediumInteger('userid');
                $table->dateTime('added')->nullable();
                $table->enum('vote', ['fun', 'dull'])->default('fun');
                $table->primary(['funid', 'userid']);
            },
            'isp' => function (Blueprint $table) {
                $table->unsignedTinyInteger('id', true);
                $table->string('name', 50)->nullable();
            },
            'links' => function (Blueprint $table) {
                $table->unsignedTinyInteger('id', true);
                $table->string('name', 30)->default('');
                $table->string('url', 255)->default('');
                $table->string('title', 50)->default('');
            },
            'prolinkclicks' => function (Blueprint $table) {
                $table->unsignedInteger('id', true);
                $table->unsignedMediumInteger('userid')->default(0);
                $table->string('ip', 64)->default('');
                $table->dateTime('added')->nullable();
            },
            'requests' => function (Blueprint $table) {
                $table->unsignedInteger('id', true);
                $table->unsignedInteger('userid')->default(0);
                $table->string('request', 225)->default('');
                $table->text('descr');
                $table->unsignedInteger('comments')->default(0);
                $table->unsignedInteger('hits')->default(0);
                $table->unsignedInteger('cat')->default(0);
                $table->unsignedInteger('filledby')->default(0);
                $table->unsignedInteger('torrentid')->default(0);
                $table->enum('finish', ['yes', 'no'])->default('no');
                $table->integer('amount')->default(0);
                $table->string('ori_descr', 255)->default('');
                $table->integer('ori_amount')->default(0);
                $table->dateTime('added')->nullable();
                $table->index('userid');
                $table->index(['finish', 'userid']);
            },
            'schools' => function (Blueprint $table) {
                $table->unsignedSmallInteger('id', true);
                $table->string('name', 50)->nullable();
            },
            'subs' => function (Blueprint $table) {
                $table->unsignedInteger('id', true);
                $table->unsignedMediumInteger('torrent_id')->default(0);
                $table->unsignedSmallInteger('lang_id')->default(0);
                $table->string('title', 255)->default('');
                $table->string('filename', 255)->default('');
                $table->dateTime('added')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->unsignedMediumInteger('uppedby')->default(0);
                $table->enum('anonymous', ['yes', 'no'])->default('no');
                $table->unsignedMediumInteger('hits')->default(0);
                $table->string('ext', 10)->default('');
                $table->index(['torrent_id', 'lang_id']);
            },
            'teams' => function (Blueprint $table) {
                $table->unsignedTinyInteger('id', true);
                $table->string('name', 30)->default('');
                $table->unsignedTinyInteger('sort_index')->default(0);
            },
            'uploadspeed' => function (Blueprint $table) {
                $table->unsignedTinyInteger('id', true);
                $table->string('name', 50)->nullable();
            },
        ] as $table => $callback) {
            if (Schema::hasTable($table)) {
                continue;
            }
            Schema::create($table, $callback);
        }
    }

    public function down(): void
    {
        foreach ([
            'uploadspeed', 'teams', 'subs', 'schools', 'requests',
            'prolinkclicks', 'links', 'isp', 'funvotes', 'fun',
            'downloadspeed', 'advertisements',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
