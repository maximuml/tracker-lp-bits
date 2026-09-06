<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W3-01: Vertical partitioning of the 118-column users table.
 *
 * Creates three partition tables that mirror columns from the users
 * table. This is phase 1 (dual-write): the columns remain on users
 * and all reads still go to users. The observer keeps the partition
 * tables in sync so that phase 2 can switch reads over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedTinyInteger('stylesheet')->default(1);
            $table->unsignedTinyInteger('caticon')->default(1);
            $table->enum('fontsize', ['small', 'medium', 'large'])->default('medium');
            $table->unsignedTinyInteger('torrentsperpage')->default(0);
            $table->unsignedTinyInteger('topicsperpage')->default(0);
            $table->unsignedTinyInteger('postsperpage')->default(0);
            $table->enum('clicktopic', ['firstpage', 'lastpage'])->default('firstpage');
            $table->enum('tooltip', ['minorimdb', 'medianimdb', 'off'])->default('off');
            $table->enum('timetype', ['timeadded', 'timealive'])->default('timealive');
            $table->enum('appendpromotion', ['highlight', 'word', 'icon', 'off'])->default('icon');
            $table->tinyInteger('appendnew')->default(1);
            $table->tinyInteger('appendpicked')->default(1);
            $table->tinyInteger('appendsticky')->default(1);
            $table->tinyInteger('avatars')->default(1);
            $table->tinyInteger('bmicon')->default(1);
            $table->tinyInteger('commentpm')->default(1);
            $table->tinyInteger('deletepms')->default(1);
            $table->tinyInteger('dlicon')->default(1);
            $table->tinyInteger('forumpost')->default(1);
            $table->tinyInteger('savepms')->default(0);
            $table->tinyInteger('showclienterror')->default(0);
            $table->tinyInteger('showcomment')->default(1);
            $table->tinyInteger('showcomnum')->default(1);
            $table->tinyInteger('showdescription')->default(1);
            $table->tinyInteger('showimdb')->default(1);
            $table->tinyInteger('showlastcom')->default(0);
            $table->tinyInteger('showlastpost')->default(0);
            $table->tinyInteger('shownfo')->default(1);
            $table->tinyInteger('showsmalldescr')->default(1);
            $table->tinyInteger('signatures')->default(1);
            $table->enum('acceptpms', ['yes', 'friends', 'no'])->default('yes');
            $table->string('notifs', 500)->nullable();
            $table->unsignedSmallInteger('lang')->default(6);
            $table->unsignedSmallInteger('sbnum')->default(70);
            $table->unsignedSmallInteger('sbrefresh')->default(120);
            $table->smallInteger('showdlnotice')->default(1);
            $table->unsignedTinyInteger('clientselect')->default(0);
            $table->text('info')->nullable();
            $table->tinyInteger('support')->default(0);
            $table->string('stafffor', 255)->default('');
            $table->string('supportfor', 255)->default('');
            $table->string('pickfor', 255)->default('');
            $table->string('supportlang', 50)->default('');
            $table->string('page', 255)->nullable();
            $table->string('signature', 800)->default('');
        });

        Schema::create('user_activity', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->dateTime('last_login')->nullable();
            $table->dateTime('last_access')->nullable();
            $table->dateTime('last_home')->nullable();
            $table->dateTime('last_offer')->nullable();
            $table->dateTime('forum_access')->nullable();
            $table->dateTime('last_staffmsg')->nullable();
            $table->dateTime('last_pm')->nullable();
            $table->dateTime('last_comment')->nullable();
            $table->dateTime('last_post')->nullable();
            $table->unsignedInteger('last_browse')->default(0);
            $table->unsignedInteger('last_music')->default(0);
            $table->unsignedInteger('last_catchup')->default(0);
            $table->dateTime('last_announce_at')->nullable();
        });

        Schema::create('user_seed_stats', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->decimal('seed_points', 20, 1)->default(0.0);
            $table->decimal('seed_points_per_hour', 20, 1)->default(0.0);
            $table->decimal('seed_bonus_per_hour', 20, 1)->default(0.0);
            $table->dateTime('seed_points_updated_at')->nullable();
            $table->dateTime('seed_time_updated_at')->nullable();
            $table->integer('seeding_torrent_count')->default(0);
            $table->bigInteger('seeding_torrent_size')->default(0);
            $table->integer('attendance_card')->default(0);
            $table->integer('offer_allowed_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_seed_stats');
        Schema::dropIfExists('user_activity');
        Schema::dropIfExists('user_preferences');
    }
};
