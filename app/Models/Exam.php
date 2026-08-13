<?php

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $begin
 * @property string|null $end
 * @property int $duration
 * @property string|null $filters
 * @property string $indexes
 * @property int $status
 * @property int $is_discovered
 * @property int $priority
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $recurring
 * @property int $type
 * @property int $success_reward_bonus
 * @property int $fail_deduct_bonus
 * @property int $max_user_count
 * @property string $background_color
 */
namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $duration
 */
class Exam extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var  list<string> */
    protected $fillable = [
        'name', 'description', 'begin', 'end', 'duration', 'status', 'is_discovered', 'filters', 'indexes', 'priority',
        'recurring', 'type', 'success_reward_bonus', 'fail_deduct_bonus', 'max_user_count', 'background_color',
    ];

    /** @var  bool */
    public $timestamps = true;

    /** @var  array<string, string> */
    protected $casts = [
        'filters' => 'array',
        'indexes' => 'array',
    ];

    const STATUS_ENABLED = 0;
    const STATUS_DISABLED = 1;

    /** @var  array<int|string, mixed> */
    public static $status = [
        self::STATUS_ENABLED => ['text' => 'Enabled'],
        self::STATUS_DISABLED => ['text' => 'Disabled'],
    ];

    const DISCOVERED_YES = 1;
    const DISCOVERED_NO = 0;

    /** @var  array<int|string, mixed> */
    public static $discovers = [
        self::DISCOVERED_NO => ['text' => 'No'],
        self::DISCOVERED_YES => ['text' => 'Yes'],
    ];

    const INDEX_UPLOADED = 1;
    const INDEX_SEED_TIME_AVERAGE = 2;
    const INDEX_DOWNLOADED = 3;
    const INDEX_SEED_BONUS = 4;
    const INDEX_SEED_POINTS = 5;
    const INDEX_UPLOAD_TORRENT_COUNT = 6;

    /** @var  array<int|string, mixed> */
    public static array $indexes = [
        self::INDEX_UPLOADED => ['name' => 'Uploaded', 'unit' => 'GB', 'source_user_field' => 'uploaded'],
        self::INDEX_DOWNLOADED => ['name' => 'Downloaded', 'unit' => 'GB', 'source_user_field' => 'downloaded'],
        self::INDEX_SEED_TIME_AVERAGE => ['name' => 'Seed time average', 'unit' => 'Hour', 'source_user_field' => 'seedtime'],
        self::INDEX_SEED_BONUS => ['name' => 'Bonus', 'unit' => '', 'source_user_field' => 'seedbonus'],
        self::INDEX_SEED_POINTS => ['name' => 'Seed points', 'unit' => '', 'source_user_field' => ''],
        self::INDEX_UPLOAD_TORRENT_COUNT => ['name' => 'Upload torrent', 'unit' => '', 'source_user_field' => ''],
    ];

    const FILTER_USER_CLASS = 'classes';
    const FILTER_USER_REGISTER_TIME_RANGE = 'register_time_range';
    const FILTER_USER_DONATE = 'donate_status';
    const FILTER_USER_REGISTER_DAYS_RANGE = 'register_days_range';

    /** @var  array<int|string, mixed> */
    public static $filters = [
        self::FILTER_USER_CLASS => ['name' => 'User class'],
        self::FILTER_USER_REGISTER_TIME_RANGE => ['name' => 'User register time range'],
        self::FILTER_USER_DONATE => ['name' => 'User donated'],
        self::FILTER_USER_REGISTER_DAYS_RANGE => ['name' => 'User register days range'],
    ];

    const RECURRING_DAILY = "Daily";
    const RECURRING_WEEKLY = "Weekly";
    const RECURRING_MONTHLY = "Monthly";

    const TYPE_EXAM = 1;
    const TYPE_TASK = 2;

    /** @return  mixed */
    protected static function booted()
    {
        static::saving(function (self $model) {
            $model->duration = (int)$model->duration;
        });
    }

    /**
     * @param  mixed  $onlyKeyValue
     * @return  array<int|string, mixed>
     */
    public static function listIndex($onlyKeyValue = false): array
    {
        $result = self::$indexes;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = \App\Support\Locale::trans("exam.index_text_{$key}", [], null);
            $value['text'] = $text;
            $keyValues[$key] = $text;
        }
        if ($onlyKeyValue) {
            return $keyValues;
        }
        return $result;
    }

    /** @return  array<int|string, mixed> */
    public static function listRecurringOptions(): array
    {
        return [
            self::RECURRING_DAILY => \App\Support\Locale::trans("exam.recurring_daily", [], null),
            self::RECURRING_WEEKLY => \App\Support\Locale::trans("exam.recurring_weekly", [], null),
            self::RECURRING_MONTHLY => \App\Support\Locale::trans("exam.recurring_monthly", [], null),
        ];
    }

    /** @return  array<int|string, mixed> */
    public static function listTypeOptions(): array
    {
        return [
            self::TYPE_EXAM => \App\Support\Locale::trans("exam.type_exam", [], null),
            self::TYPE_TASK => \App\Support\Locale::trans("exam.type_task", [], null),
        ];
    }

    /** @return  mixed */
    public function getTypeTextAttribute()
    {
        return self::listTypeOptions()[$this->type] ?? "";
    }


    protected function getRecurringTextAttribute(): string
    {
        $options = self::listRecurringOptions();
        return $options[$this->recurring] ?? '';
    }

    public function getStatusTextAttribute(): string
    {
        return $this->status == self::STATUS_ENABLED ? \App\Support\Locale::trans('label.enabled', [], null) : \App\Support\Locale::trans('label.disabled', [], null);
    }

    public function getIsDiscoveredTextAttribute(): string
    {
        return self::$discovers[$this->is_discovered]['text'] ?? '';
    }

    public function getDurationTextAttribute(): string
    {
        if ($this->duration > 0) {
            return $this->duration . ' Days';
        }
        return '';
    }

    public function getIndexFormattedAttribute(): string
    {
        $indexes = $this->indexes;
        $arr = [];
        foreach ($indexes as $index) {
            if (isset($index['checked']) && $index['checked']) {
                $arr[] = sprintf(
                    '%s: %s %s',
                    \App\Support\Locale::trans("exam.index_text_{$index['index']}", [], null),
                    $index['require_value'],
                    self::$indexes[$index['index']]['unit'] ?? ''
                );
            }
        }
        return implode("<br/>", $arr);
    }

    public function getFilterFormattedAttribute(): string
    {
        $currentFilters = $this->filters;
        $arr = [];
        $filter = self::FILTER_USER_CLASS;
        if (!empty($currentFilters[$filter])) {
            $classes = collect(User::$classes)->only($currentFilters[$filter]);
            $arr[] = sprintf(
                '%s: %s',
                \App\Support\Locale::trans("exam.filters.{$filter}", [], null), $classes->map(fn ($value, $key) => User::getClassText($key))->implode(', ')
            );
        }

        $filter = self::FILTER_USER_REGISTER_TIME_RANGE;
        if (!empty($currentFilters[$filter])) {
            $range = $currentFilters[$filter];
            if (!empty($range[0]) || !empty($range[1])) {
                $arr[] = sprintf(
                    "%s: <br/>%s ~ %s",
                    \App\Support\Locale::trans("exam.filters.{$filter}", [], null),
                    $range[0] ? Carbon::parse($range[0])->toDateTimeString() : '--',
                    $range[1] ? Carbon::parse($range[1])->toDateTimeString() : '--'
                );
            }
        }

        $filter = self::FILTER_USER_REGISTER_DAYS_RANGE;
        if (!empty($currentFilters[$filter])) {
            $range = $currentFilters[$filter];
            if (!empty($range[0]) || !empty($range[1])) {
                $arr[] = sprintf(
                    "%s: %s ~ %s",
                    \App\Support\Locale::trans("exam.filters.{$filter}", [], null),
                    $range[0] ?? "--",
                    $range[1] ?? '--'
                );
            }
        }

        $filter = self::FILTER_USER_DONATE;
        if (!empty($currentFilters[$filter])) {
            $donateStatus = collect(User::$donateStatus)->only($currentFilters[$filter]);
            $arr[] = sprintf('%s: %s', \App\Support\Locale::trans("exam.filters.{$filter}", [], null), $donateStatus->pluck('text')->implode(', '));
        }

        return implode("<br/>", $arr);
    }

    public function getBeginForUser(): Carbon
    {
        if (!empty($this->begin)) {
            return Carbon::parse($this->begin);
        }
        if (!empty($this->recurring)) {
            return $this->getRecurringBegin(Carbon::now());
        }
        return Carbon::now();
    }

    public function getEndForUser(): Carbon
    {
        if (!empty($this->end)) {
            return Carbon::parse($this->end);
        }
        if (!empty($this->duration)) {
            return $this->getBeginForUser()->clone()->addDays((int)$this->duration);
        }
        if (!empty($this->recurring)) {
            return $this->getRecurringEnd(Carbon::now());
        }
        throw new \RuntimeException(\App\Support\Locale::trans("exam.time_condition_invalid", [], null));
    }

    /** @param  Carbon  $time */
    public function getRecurringBegin(Carbon $time): Carbon
    {
        $time = $time->copy();
        $recurring = $this->recurring;
        if ($recurring == self::RECURRING_WEEKLY) {
            return $time->startOfWeek();
        } elseif ($recurring == self::RECURRING_MONTHLY) {
            return $time->startOfMonth();
        } elseif ($recurring == self::RECURRING_DAILY) {
            return $time->startOfDay();
        }
        throw new \RuntimeException("Invalid recurring: $recurring");
    }

    /** @param  Carbon  $time */
    public function getRecurringEnd(Carbon $time): Carbon
    {
        $time = $time->copy();
        $recurring = $this->recurring;
        if ($recurring == self::RECURRING_WEEKLY) {
            return $time->endOfWeek();
        } elseif ($recurring == self::RECURRING_MONTHLY) {
            return $time->endOfMonth();
        } elseif ($recurring == self::RECURRING_DAILY) {
            return $time->endOfDay();
        }
        throw new \RuntimeException("Invalid recurring: $recurring");
    }

    /** @param  string  $result */
    public function getMessageSubjectTransKey(string $result): string
    {
        return match ($this->type) {
            self::TYPE_EXAM => "exam.checkout_{$result}_message_subject_for_exam",
            self::TYPE_TASK => "exam.checkout_{$result}_message_subject_for_task",
            default => throw new \RuntimeException("Invalid type: " . $this->type)
        };
    }

    /** @param  string  $result */
    public function getMessageContentTransKey(string $result): string
    {
        return match ($this->type) {
            self::TYPE_EXAM => "exam.checkout_{$result}_message_content_for_exam",
            self::TYPE_TASK => "exam.checkout_{$result}_message_content_for_task",
            default => throw new \RuntimeException("Invalid type: " . $this->type)
        };
    }

    /** @param  string  $result */
    public function getPassResultTransKey(string $result): string
    {
        return match ($this->type) {
            self::TYPE_EXAM => "exam.result_{$result}_for_exam",
            self::TYPE_TASK => "exam.result_{$result}_for_task",
            default => throw new \RuntimeException("Invalid type: " . $this->type)
        };
    }

    public function isTypeExam(): bool
    {
        return $this->type == self::TYPE_EXAM;
    }

    public function isTypeTask(): bool
    {
        return $this->type == self::TYPE_TASK;
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsToMany<User, $this> */
    public function users()
    {
        return $this->belongsToMany(User::class, "exam_users", "exam_id", "uid");
    }

    /** @return  mixed */
    public function onGoingUsers()
    {
        return $this->users()->wherePivot("status", ExamUser::STATUS_NORMAL);
    }

}
