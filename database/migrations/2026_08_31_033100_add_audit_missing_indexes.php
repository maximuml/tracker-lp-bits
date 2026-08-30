<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds 23 missing indexes identified in the database audit.
     * These columns are used in WHERE/JOIN/ORDER BY clauses but
     * have no index, causing full table scans on large tables.
     */
    public function up(): void
    {
        // adclicks: ad tracking queries by user and ad
        Schema::table('adclicks', function (Blueprint $table) {
            $table->index('userid', 'adclicks_userid_index');
            $table->index('adid', 'adclicks_adid_index');
        });

        // chronicle: user activity history lookups
        Schema::table('chronicle', function (Blueprint $table) {
            $table->index('userid', 'chronicle_userid_index');
        });

        // complain_replies: user-based reply queries
        Schema::table('complain_replies', function (Blueprint $table) {
            $table->index('userid', 'complain_replies_userid_index');
        });

        // forummods: moderator lookup by user
        Schema::table('forummods', function (Blueprint $table) {
            $table->index('userid', 'forummods_userid_index');
        });

        // invites: registration tracking by invitee
        Schema::table('invites', function (Blueprint $table) {
            $table->index('invitee_register_uid', 'invites_invitee_register_uid_index');
        });

        // reports: report chain lookups
        Schema::table('reports', function (Blueprint $table) {
            $table->index('reportid', 'reports_reportid_index');
        });

        // snatched: bonus and hit-and-run lookups
        Schema::table('snatched', function (Blueprint $table) {
            $table->index('buy_log_id', 'snatched_buy_log_id_index');
            $table->index('hit_and_run_id', 'snatched_hit_and_run_id_index');
        });

        // suggest: user suggestion lookups
        Schema::table('suggest', function (Blueprint $table) {
            $table->index('userid', 'suggest_userid_index');
        });

        // username_change_logs: user history lookups
        Schema::table('username_change_logs', function (Blueprint $table) {
            $table->index('uid', 'username_change_logs_uid_index');
        });

        // categories: icon filtering
        Schema::table('categories', function (Blueprint $table) {
            $table->index('icon_id', 'categories_icon_id_index');
        });

        // faq: language and link filtering
        Schema::table('faq', function (Blueprint $table) {
            $table->index('lang_id', 'faq_lang_id_index');
            $table->index('link_id', 'faq_link_id_index');
        });

        // rules: language filtering
        Schema::table('rules', function (Blueprint $table) {
            $table->index('lang_id', 'rules_lang_id_index');
        });

        // resreq: torrent relationship lookups
        Schema::table('resreq', function (Blueprint $table) {
            $table->index('torrentid', 'resreq_torrentid_index');
        });

        // users: tracker URL filtering
        Schema::table('users', function (Blueprint $table) {
            $table->index('tracker_url_id', 'users_tracker_url_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adclicks', function (Blueprint $table) {
            $table->dropIndex('adclicks_userid_index');
            $table->dropIndex('adclicks_adid_index');
        });

        Schema::table('chronicle', function (Blueprint $table) {
            $table->dropIndex('chronicle_userid_index');
        });

        Schema::table('complain_replies', function (Blueprint $table) {
            $table->dropIndex('complain_replies_userid_index');
        });

        Schema::table('forummods', function (Blueprint $table) {
            $table->dropIndex('forummods_userid_index');
        });

        Schema::table('invites', function (Blueprint $table) {
            $table->dropIndex('invites_invitee_register_uid_index');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_reportid_index');
        });

        Schema::table('snatched', function (Blueprint $table) {
            $table->dropIndex('snatched_buy_log_id_index');
            $table->dropIndex('snatched_hit_and_run_id_index');
        });

        Schema::table('suggest', function (Blueprint $table) {
            $table->dropIndex('suggest_userid_index');
        });

        Schema::table('username_change_logs', function (Blueprint $table) {
            $table->dropIndex('username_change_logs_uid_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_icon_id_index');
        });

        Schema::table('faq', function (Blueprint $table) {
            $table->dropIndex('faq_lang_id_index');
            $table->dropIndex('faq_link_id_index');
        });

        Schema::table('rules', function (Blueprint $table) {
            $table->dropIndex('rules_lang_id_index');
        });

        Schema::table('resreq', function (Blueprint $table) {
            $table->dropIndex('resreq_torrentid_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_tracker_url_id_index');
        });
    }
};
