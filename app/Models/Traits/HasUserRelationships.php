<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Country;
use App\Models\Exam;
use App\Models\ExamUser;
use App\Models\HitAndRun;
use App\Models\Invite;
use App\Models\Language;
use App\Models\Medal;
use App\Models\Message;
use App\Models\Peer;
use App\Models\PollAnswer;
use App\Models\Post;
use App\Models\Reward;
use App\Models\Snatch;
use App\Models\Thank;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserMedal;
use App\Models\UserMeta;
use App\Models\UserModifyLog;
use App\Models\UsernameChangeLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * All Eloquent relationships for the User model, extracted to keep the
 * main User class focused on domain logic.
 */
trait HasUserRelationships
{
    /**
     * @return HasMany<ExamUser, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(ExamUser::class, 'uid');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'lang');
    }

    /** @return HasOne<Invite, $this> */
    public function invitee_code(): HasOne
    {
        return $this->hasOne(Invite::class, 'invitee_register_uid');
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country');
    }

    /** @return HasMany<Invite, $this> */
    public function temporary_invites(): HasMany
    {
        return $this->hasMany(Invite::class, 'inviter')
            ->where('invitee', '')
            ->whereNotNull('expired_at')
            ->where('expired_at', '>=', Carbon::now());
    }

    /** @return HasMany<Message, $this> */
    public function send_messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender');
    }

    /** @return HasMany<Message, $this> */
    public function receive_messages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'user');
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'userid');
    }

    /**
     * @return HasMany<Torrent, $this>
     */
    public function torrents(): HasMany
    {
        return $this->hasMany(Torrent::class, 'owner');
    }

    /** @return HasMany<Bookmark, $this> */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class, 'userid');
    }

    /** @return HasManyThrough<Torrent, Peer, $this> */
    public function peers_torrents(): HasManyThrough
    {
        return $this->hasManyThrough(
            Torrent::class,
            Peer::class,
            'userid',
            'id',
            'id',
            'torrent');
    }

    /** @return HasManyThrough<Torrent, Snatch, $this> */
    public function snatched_torrents(): HasManyThrough
    {
        return $this->hasManyThrough(
            Torrent::class,
            Snatch::class,
            'userid',
            'id',
            'id',
            'torrentid');
    }

    /** @return HasManyThrough<Torrent, Peer, $this> */
    public function seeding_torrents(): HasManyThrough
    {
        return $this->peers_torrents()->where('peers.seeder', Peer::SEEDER_YES);
    }

    /** @return HasManyThrough<Torrent, Peer, $this> */
    public function leeching_torrents(): HasManyThrough
    {
        return $this->peers_torrents()->where('peers.seeder', Peer::SEEDER_NO);
    }

    /** @return HasManyThrough<Torrent, Snatch, $this> */
    public function completed_torrents(): HasManyThrough
    {
        return $this->snatched_torrents()->where('snatched.finished', Snatch::FINISHED_YES);
    }

    /** @return HasManyThrough<Torrent, Snatch, $this> */
    public function incomplete_torrents(): HasManyThrough
    {
        return $this->snatched_torrents()->where('snatched.finished', Snatch::FINISHED_NO);
    }

    /** @return HasMany<HitAndRun, $this> */
    public function hitAndRuns(): HasMany
    {
        return $this->hasMany(HitAndRun::class, 'uid');
    }

    /**
     * @return BelongsToMany<Medal, $this>
     */
    public function medals(): BelongsToMany
    {
        return $this->belongsToMany(Medal::class, 'user_medals', 'uid', 'medal_id')
            ->withPivot(['id', 'expire_at', 'status', 'priority', 'bonus_addition_expire_at'])
            ->withTimestamps()
            ->orderByPivot('priority', 'desc');
    }

    /**
     * @return BelongsToMany<Medal, $this>
     */
    public function valid_medals(): BelongsToMany
    {
        return $this->medals()->where(function ($query) {
            $query->whereNull('user_medals.expire_at')->orWhere('user_medals.expire_at', '>=', Carbon::now());
        });
    }

    /**
     * @return BelongsToMany<Medal, $this>
     */
    public function wearing_medals(): BelongsToMany
    {
        return $this->valid_medals()->where('user_medals.status', UserMedal::STATUS_WEARING);
    }

    /** @return HasMany<Reward, $this> */
    public function reward_torrent_logs(): HasMany
    {
        return $this->hasMany(Reward::class, 'userid');
    }

    /** @return HasMany<Thank, $this> */
    public function thank_torrent_logs(): HasMany
    {
        return $this->hasMany(Thank::class, 'userid');
    }

    /** @return HasMany<PollAnswer, $this> */
    public function poll_answers(): HasMany
    {
        return $this->hasMany(PollAnswer::class, 'userid');
    }

    /** @return HasMany<UserMeta, $this> */
    public function metas(): HasMany
    {
        return $this->hasMany(UserMeta::class, 'uid');
    }

    /** @return HasMany<UsernameChangeLog, $this> */
    public function usernameChangeLogs(): HasMany
    {
        return $this->hasMany(UsernameChangeLog::class, 'uid');
    }

    /** @return BelongsToMany<Exam, $this> */
    public function examAndTasks(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_users', 'uid', 'exam_id');
    }

    /** @return BelongsToMany<Exam, $this> */
    public function onGoingExamAndTasks(): BelongsToMany
    {
        return $this->examAndTasks()->wherePivot('status', ExamUser::STATUS_NORMAL);
    }

    /** @return HasMany<UserModifyLog, $this> */
    public function modifyLogs(): HasMany
    {
        return $this->hasMany(UserModifyLog::class, 'user_id');
    }
}
