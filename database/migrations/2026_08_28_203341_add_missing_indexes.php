<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // High priority indexes

        // messages: inbox unread count and list queries
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['receiver', 'unread', 'added'], 'messages_receiver_unread_added_index');
        });

        // snatched: scopeIsFinished / scopeIsNotFinished filters
        Schema::table('snatched', function (Blueprint $table) {
            $table->index(['torrentid', 'finished', 'completedat'], 'snatched_torrentid_finished_completedat_index');
        });

        // offervotes: vote counts / existence checks by offerid
        Schema::table('offervotes', function (Blueprint $table) {
            $table->index('offerid', 'offervotes_offerid_index');
        });

        // user_metas: getUserMeta filters on status and deadline
        Schema::table('user_metas', function (Blueprint $table) {
            $table->index(['uid', 'status', 'deadline'], 'user_metas_uid_status_deadline_index');
        });

        // pmboxes: message box lookups by userid (no index at all)
        Schema::table('pmboxes', function (Blueprint $table) {
            $table->index('userid', 'pmboxes_userid_index');
        });

        // hit_and_runs: torrent_id alone (existing unique has uid as leftmost)
        Schema::table('hit_and_runs', function (Blueprint $table) {
            $table->index('torrent_id', 'hit_and_runs_torrent_id_index');
        });

        // shoutbox: listing filters by userid (no index at all)
        Schema::table('shoutbox', function (Blueprint $table) {
            $table->index('userid', 'shoutbox_userid_index');
        });

        // user_passkeys: lookups and deletes by user_id (no index at all)
        Schema::table('user_passkeys', function (Blueprint $table) {
            $table->index('user_id', 'user_passkeys_user_id_index');
        });

        // torrents_state: scopeActive / scopeUpcoming filters
        Schema::table('torrents_state', function (Blueprint $table) {
            $table->index(['global_sp_state', 'begin', 'deadline'], 'torrents_state_global_sp_state_begin_deadline_index');
        });

        // peers: dashboard seeder/leecher counts
        Schema::table('peers', function (Blueprint $table) {
            $table->index(['seeder', 'last_action'], 'peers_seeder_last_action_index');
        });

        // Medium priority indexes

        // users: email lookups (only username is unique)
        Schema::table('users', function (Blueprint $table) {
            $table->index('email', 'users_email_index');
        });

        // users: scopeDonating filters donor + donoruntil
        Schema::table('users', function (Blueprint $table) {
            $table->index(['donor', 'donoruntil'], 'users_donor_donoruntil_index');
        });

        // bonus_logs: repository filters by business_type
        Schema::table('bonus_logs', function (Blueprint $table) {
            $table->index(['business_type', 'uid'], 'bonus_logs_business_type_uid_index');
        });

        // sitelog: uid column added but never indexed
        Schema::table('sitelog', function (Blueprint $table) {
            $table->index('uid', 'sitelog_uid_index');
        });

        // complain_replies: belongs to complains but no FK index
        Schema::table('complain_replies', function (Blueprint $table) {
            $table->index('complain', 'complain_replies_complain_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_receiver_unread_added_index');
        });

        Schema::table('snatched', function (Blueprint $table) {
            $table->dropIndex('snatched_torrentid_finished_completedat_index');
        });

        Schema::table('offervotes', function (Blueprint $table) {
            $table->dropIndex('offervotes_offerid_index');
        });

        Schema::table('user_metas', function (Blueprint $table) {
            $table->dropIndex('user_metas_uid_status_deadline_index');
        });

        Schema::table('pmboxes', function (Blueprint $table) {
            $table->dropIndex('pmboxes_userid_index');
        });

        Schema::table('hit_and_runs', function (Blueprint $table) {
            $table->dropIndex('hit_and_runs_torrent_id_index');
        });

        Schema::table('shoutbox', function (Blueprint $table) {
            $table->dropIndex('shoutbox_userid_index');
        });

        Schema::table('user_passkeys', function (Blueprint $table) {
            $table->dropIndex('user_passkeys_user_id_index');
        });

        Schema::table('torrents_state', function (Blueprint $table) {
            $table->dropIndex('torrents_state_global_sp_state_begin_deadline_index');
        });

        Schema::table('peers', function (Blueprint $table) {
            $table->dropIndex('peers_seeder_last_action_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_donor_donoruntil_index');
        });

        Schema::table('bonus_logs', function (Blueprint $table) {
            $table->dropIndex('bonus_logs_business_type_uid_index');
        });

        Schema::table('sitelog', function (Blueprint $table) {
            $table->dropIndex('sitelog_uid_index');
        });

        Schema::table('complain_replies', function (Blueprint $table) {
            $table->dropIndex('complain_replies_complain_index');
        });
    }
};
