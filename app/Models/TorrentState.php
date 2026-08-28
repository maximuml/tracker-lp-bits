<?php

declare(strict_types=1);

/**
 * @property int $global_sp_state
 * @property int $id
 * @property string|null $deadline
 * @property string|null $remark
 * @property int $notice_days
 * @property string|null $begin
 */

namespace App\Models;

use App\Enums\TorrentPromotion;
use App\Enums\TorrentStateNotice;
use App\Models\Traits\NexusActivityLogTrait;
use App\Support\Cache as AppCache;
use App\Support\Events;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class TorrentState extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var list<string> */
    protected $fillable = ['global_sp_state', 'deadline', 'begin', 'remark', 'notice_days'];

    /** @var string */
    protected $table = 'torrents_state';

    /** @var array<string, string> */
    protected $casts = [
        'begin' => 'datetime',
        'deadline' => 'datetime',
        'notice_days' => 'integer',
    ];

    /** @return  mixed */
    protected static function booted()
    {
        parent::booted();

        static::saving(function (TorrentState $state) {
            $state->validateTimeRange();
            $state->ensureNoOverlap();
        });

        static::saved(function () {
            static::flushCache();
        });

        static::deleted(function () {
            static::flushCache();
        });
    }

    /** @return  mixed */
    public function getGlobalSpStateTextAttribute()
    {
        return TorrentPromotion::fromIntSafe((int) $this->global_sp_state)->label();
    }

    public function getNoticeDaysTextAttribute(): string
    {
        return self::noticeOptions()[$this->notice_days] ?? '';
    }

    /**
     * @param  Builder<TorrentState>  $query
     * @return Builder<TorrentState>
     */
    public function scopeActive(Builder $query, ?Carbon $moment = null): Builder
    {
        $moment = $moment ?? Carbon::now();

        return $query
            ->where('global_sp_state', '!=', TorrentPromotion::NORMAL->value)
            ->where(function (Builder $query) use ($moment) {
                $query->whereNull('begin')->orWhere('begin', '<=', $moment);
            })
            ->where(function (Builder $query) use ($moment) {
                $query->whereNull('deadline')->orWhere('deadline', '>=', $moment);
            })
            ->orderBy('begin')
            ->orderBy('id');
    }

    /**
     * @param  Builder<TorrentState>  $query
     * @return Builder<TorrentState>
     */
    public function scopeUpcoming(Builder $query, ?Carbon $moment = null): Builder
    {
        $moment = $moment ?? Carbon::now();

        return $query
            ->where('global_sp_state', '!=', TorrentPromotion::NORMAL->value)
            ->whereNotNull('begin')
            ->where('begin', '>', $moment)
            ->orderBy('begin')
            ->orderBy('id');
    }

    public static function current(?Carbon $moment = null): ?self
    {
        return self::query()->active($moment)->first();
    }

    public static function next(?Carbon $moment = null): ?self
    {
        return self::query()->upcoming($moment)->first();
    }

    /** @return  array<int|string, mixed> */
    public static function cachedStates(): array
    {
        return Cache::remember(Setting::TORRENT_GLOBAL_STATE_CACHE_KEY, 600, function () {
            return self::query()
                ->where('global_sp_state', '!=', TorrentPromotion::NORMAL->value)
                ->orderByRaw('begin is null')
                ->orderBy('begin')
                ->orderBy('id')
                ->get()
                ->toArray();
        });
    }

    public static function flushCache(): void
    {
        Logger::writeWithContext((string) ('cache_del: '.Setting::TORRENT_GLOBAL_STATE_CACHE_KEY), (string) 'info', (bool) false);
        AppCache::forgetWithLocales(Setting::TORRENT_GLOBAL_STATE_CACHE_KEY);
        Logger::writeWithContext((string) 'publish_model_event: global_promotion_state_updated', (string) 'info', (bool) false);
        Events::publishModel('global_promotion_state_updated', 0, '');
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function resolveTimeline(?Carbon $moment = null): array
    {
        $moment = $moment ?? Carbon::now();
        $states = self::cachedStates();
        $current = null;
        $upcoming = null;

        foreach ($states as $state) {
            $begin = self::parseDateTimeValue($state['begin'] ?? null);
            $deadline = self::parseDateTimeValue($state['deadline'] ?? null);
            $noticeDays = (int) ($state['notice_days'] ?? TorrentStateNotice::NONE->value);

            $hasBegun = ! $begin || $begin->lessThanOrEqualTo($moment);
            $notExpired = ! $deadline || $deadline->greaterThanOrEqualTo($moment);

            if ($hasBegun && $notExpired) {
                if (! $current) {
                    $current = $state;
                }

                continue;
            }

            if ($begin && $begin->greaterThan($moment)) {
                if (! self::isWithinNoticeWindow($begin, $noticeDays, $moment)) {
                    continue;
                }
                if (! $upcoming) {
                    $upcoming = $state;

                    continue;
                }
                $upcomingBegin = self::parseDateTimeValue($upcoming['begin'] ?? null);
                if ($upcomingBegin && $begin->lessThan($upcomingBegin)) {
                    $upcoming = $state;
                }
            }
        }

        return [
            'current' => $current,
            'upcoming' => $upcoming,
        ];
    }

    protected function validateTimeRange(): void
    {
        $begin = self::parseDateTimeValue($this->begin);
        $deadline = self::parseDateTimeValue($this->deadline);

        if ($begin && $deadline && $deadline->lessThanOrEqualTo($begin)) {
            throw ValidationException::withMessages([
                self::errorFieldKey('deadline') => __('label.torrent_state.deadline_after_begin'),
            ]);
        }
    }

    protected function ensureNoOverlap(): void
    {
        self::validateNoOverlap($this->attributesToArray(), $this->id);
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function getRangeForComparison(TorrentState $state): array
    {
        $min = Carbon::createFromTimestamp(0);
        $max = Carbon::create(9999, 12, 31, 23, 59, 59);

        $begin = self::parseDateTimeValue($state->begin) ?? $min;

        $deadline = self::parseDateTimeValue($state->deadline) ?? $max;

        return [
            'begin' => $begin,
            'end' => $deadline,
        ];
    }

    protected static function parseDateTimeValue(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (empty($value) || $value === '0000-00-00 00:00:00') {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * @param  array<int|string, mixed>  $attributes
     */
    public static function validateNoOverlap(array $attributes, ?int $ignoreId = null): void
    {
        $globalState = (int) Arr::get($attributes, 'global_sp_state', TorrentPromotion::NORMAL->value);
        if ($globalState === TorrentPromotion::NORMAL->value) {
            return;
        }

        $range = self::getRangeForArray($attributes);

        $conflicts = self::query()
            ->where('global_sp_state', '!=', TorrentPromotion::NORMAL->value)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->get(['id', 'begin', 'deadline']);

        $beginConflict = $conflicts->first(function (TorrentState $state) use ($range) {
            $other = $state->getRangeForComparison($state);

            return $range['begin']->greaterThanOrEqualTo($other['begin']) && $range['begin']->lessThanOrEqualTo($other['end']);
        });

        $endConflict = $conflicts->first(function (TorrentState $state) use ($range) {
            $other = $state->getRangeForComparison($state);

            return $range['end']->greaterThanOrEqualTo($other['begin']) && $range['end']->lessThanOrEqualTo($other['end']);
        });

        $coverageConflict = $conflicts->first(function (TorrentState $state) use ($range) {
            $other = $state->getRangeForComparison($state);

            return $range['begin']->lt($other['begin']) && $range['end']->gt($other['end']);
        });

        if ($beginConflict || $endConflict || $coverageConflict) {
            $errors = [];

            if ($beginConflict) {
                $errors[self::errorFieldKey('begin')] = self::buildOverlapMessage($beginConflict);
            }

            if ($endConflict) {
                $errors[self::errorFieldKey('deadline')] = self::buildOverlapMessage($endConflict);
            }

            if (empty($errors) && $coverageConflict) {
                $msg = self::buildOverlapMessage($coverageConflict);
                $errors[self::errorFieldKey('begin')] = $msg;
                $errors[self::errorFieldKey('deadline')] = $msg;
            }

            if (empty($errors)) {
                $msg = __('label.torrent_state.time_overlaps');
                $errors[self::errorFieldKey('begin')] = $msg;
                $errors[self::errorFieldKey('deadline')] = $msg;
            }

            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int|string, mixed>  $attributes
     * @return array<int|string, mixed>
     */
    protected static function getRangeForArray(array $attributes): array
    {
        $min = Carbon::createFromTimestamp(0);
        $max = Carbon::create(9999, 12, 31, 23, 59, 59);

        $begin = self::parseDateTimeValue($attributes['begin'] ?? null) ?? $min;
        $deadline = self::parseDateTimeValue($attributes['deadline'] ?? null) ?? $max;

        return [
            'begin' => $begin,
            'end' => $deadline,
        ];
    }

    protected static function errorFieldKey(string $field): string
    {
        $prefix = 'mountedActions.0.data.';

        return $prefix.$field;
    }

    protected static function buildOverlapMessage(TorrentState $conflict): string
    {
        $begin = self::parseDateTimeValue($conflict->begin);
        $deadline = self::parseDateTimeValue($conflict->deadline);

        $beginText = $begin ? $begin->toDateTimeString() : '-∞';
        $deadlineText = $deadline ? $deadline->toDateTimeString() : '∞';

        return __('label.torrent_state.time_overlaps_with', [
            'id' => $conflict->id,
            'begin' => $beginText,
            'end' => $deadlineText,
        ]);
    }

    /** @return  array<int|string, mixed> */
    public static function noticeOptions(): array
    {
        return [
            TorrentStateNotice::NONE->value => __('label.torrent_state.notice_none'),
            1 => __('label.torrent_state.notice_day', ['days' => 1]),
            3 => __('label.torrent_state.notice_day', ['days' => 3]),
            7 => __('label.torrent_state.notice_day', ['days' => 7]),
            15 => __('label.torrent_state.notice_day', ['days' => 15]),
            30 => __('label.torrent_state.notice_day', ['days' => 30]),
            TorrentStateNotice::UNLIMITED->value => __('label.torrent_state.notice_unlimited'),
        ];
    }

    protected static function isWithinNoticeWindow(?Carbon $begin, int $noticeDays, Carbon $now): bool
    {
        if (! $begin) {
            return true;
        }
        if ($noticeDays === TorrentStateNotice::NONE->value) {
            return false;
        }
        if ($noticeDays === TorrentStateNotice::UNLIMITED->value) {
            return true;
        }

        return $begin->copy()->subDays($noticeDays)->lessThanOrEqualTo($now);
    }
}
