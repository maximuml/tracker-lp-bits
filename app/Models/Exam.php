<?php

declare(strict_types=1);

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

use App\Models\Traits\HasExamAccessors;
use App\Models\Traits\HasExamRelationships;
use App\Models\Traits\NexusActivityLogTrait;
use App\Support\Locale;
use Carbon\Carbon;

/**
 * @property int $duration
 */
class Exam extends NexusModel
{
    use HasExamAccessors, HasExamRelationships, NexusActivityLogTrait;

    /** @var list<string> */
    protected $fillable = [
        'name', 'description', 'begin', 'end', 'duration', 'status', 'is_discovered', 'filters', 'indexes', 'priority',
        'recurring', 'type', 'success_reward_bonus', 'fail_deduct_bonus', 'max_user_count', 'background_color',
    ];

    /** @var bool */
    public $timestamps = true;

    /** @var array<string, string> */
    protected $casts = [
        'filters' => 'array',
        'indexes' => 'array',
    ];

    /** @deprecated Use App\Enums\ExamStatus enum instead. */
    const STATUS_ENABLED = 0;

    /** @deprecated Use App\Enums\ExamStatus enum instead. */
    const STATUS_DISABLED = 1;

    /** @var array<int|string, mixed> */
    public static $status = [
        self::STATUS_ENABLED => ['text' => 'Enabled'],
        self::STATUS_DISABLED => ['text' => 'Disabled'],
    ];

    /** @deprecated Use App\Enums\ExamDiscovered enum instead. */
    const DISCOVERED_YES = 1;

    /** @deprecated Use App\Enums\ExamDiscovered enum instead. */
    const DISCOVERED_NO = 0;

    /** @var array<int|string, mixed> */
    public static $discovers = [
        self::DISCOVERED_NO => ['text' => 'No'],
        self::DISCOVERED_YES => ['text' => 'Yes'],
    ];

    /** @deprecated Use App\Enums\ExamIndex enum instead. */
    const INDEX_UPLOADED = 1;

    /** @deprecated Use App\Enums\ExamIndex enum instead. */
    const INDEX_SEED_TIME_AVERAGE = 2;

    /** @deprecated Use App\Enums\ExamIndex enum instead. */
    const INDEX_DOWNLOADED = 3;

    /** @deprecated Use App\Enums\ExamIndex enum instead. */
    const INDEX_SEED_BONUS = 4;

    /** @deprecated Use App\Enums\ExamIndex enum instead. */
    const INDEX_SEED_POINTS = 5;

    /** @deprecated Use App\Enums\ExamIndex enum instead. */
    const INDEX_UPLOAD_TORRENT_COUNT = 6;

    /** @var array<int|string, mixed> */
    public static array $indexes = [
        self::INDEX_UPLOADED => ['name' => 'Uploaded', 'unit' => 'GB', 'source_user_field' => 'uploaded'],
        self::INDEX_DOWNLOADED => ['name' => 'Downloaded', 'unit' => 'GB', 'source_user_field' => 'downloaded'],
        self::INDEX_SEED_TIME_AVERAGE => ['name' => 'Seed time average', 'unit' => 'Hour', 'source_user_field' => 'seedtime'],
        self::INDEX_SEED_BONUS => ['name' => 'Bonus', 'unit' => '', 'source_user_field' => 'seedbonus'],
        self::INDEX_SEED_POINTS => ['name' => 'Seed points', 'unit' => '', 'source_user_field' => ''],
        self::INDEX_UPLOAD_TORRENT_COUNT => ['name' => 'Upload torrent', 'unit' => '', 'source_user_field' => ''],
    ];

    /** @deprecated Use App\Enums\ExamFilterUser enum instead. */
    const FILTER_USER_CLASS = 'classes';

    /** @deprecated Use App\Enums\ExamFilterUser enum instead. */
    const FILTER_USER_REGISTER_TIME_RANGE = 'register_time_range';

    /** @deprecated Use App\Enums\ExamFilterUser enum instead. */
    const FILTER_USER_DONATE = 'donate_status';

    /** @deprecated Use App\Enums\ExamFilterUser enum instead. */
    const FILTER_USER_REGISTER_DAYS_RANGE = 'register_days_range';

    /** @var array<int|string, mixed> */
    public static $filters = [
        self::FILTER_USER_CLASS => ['name' => 'User class'],
        self::FILTER_USER_REGISTER_TIME_RANGE => ['name' => 'User register time range'],
        self::FILTER_USER_DONATE => ['name' => 'User donated'],
        self::FILTER_USER_REGISTER_DAYS_RANGE => ['name' => 'User register days range'],
    ];

    /** @deprecated Use App\Enums\ExamRecurring enum instead. */
    const RECURRING_DAILY = 'Daily';

    /** @deprecated Use App\Enums\ExamRecurring enum instead. */
    const RECURRING_WEEKLY = 'Weekly';

    /** @deprecated Use App\Enums\ExamRecurring enum instead. */
    const RECURRING_MONTHLY = 'Monthly';

    /** @deprecated Use App\Enums\ExamType enum instead. */
    const TYPE_EXAM = 1;

    /** @deprecated Use App\Enums\ExamType enum instead. */
    const TYPE_TASK = 2;

    /** @return  mixed */
    protected static function booted()
    {
        static::saving(function (self $model) {
            $model->duration = (int) $model->duration;
        });
    }

    /**
     * @param  mixed  $onlyKeyValue
     * @return array<int|string, mixed>
     */
    public static function listIndex($onlyKeyValue = false): array
    {
        $result = self::$indexes;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = Locale::trans("exam.index_text_{$key}", [], null);
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
            self::RECURRING_DAILY => Locale::trans('exam.recurring_daily', [], null),
            self::RECURRING_WEEKLY => Locale::trans('exam.recurring_weekly', [], null),
            self::RECURRING_MONTHLY => Locale::trans('exam.recurring_monthly', [], null),
        ];
    }

    /** @return  array<int|string, mixed> */
    public static function listTypeOptions(): array
    {
        return [
            self::TYPE_EXAM => Locale::trans('exam.type_exam', [], null),
            self::TYPE_TASK => Locale::trans('exam.type_task', [], null),
        ];
    }

    public function getBeginForUser(): Carbon
    {
        if (! empty($this->begin)) {
            return Carbon::parse($this->begin);
        }
        if (! empty($this->recurring)) {
            return $this->getRecurringBegin(Carbon::now());
        }

        return Carbon::now();
    }

    public function getEndForUser(): Carbon
    {
        if (! empty($this->end)) {
            return Carbon::parse($this->end);
        }
        if (! empty($this->duration)) {
            return $this->getBeginForUser()->clone()->addDays((int) $this->duration);
        }
        if (! empty($this->recurring)) {
            return $this->getRecurringEnd(Carbon::now());
        }
        throw new \RuntimeException(Locale::trans('exam.time_condition_invalid', [], null));
    }

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

    public function getMessageSubjectTransKey(string $result): string
    {
        return match ($this->type) {
            self::TYPE_EXAM => "exam.checkout_{$result}_message_subject_for_exam",
            self::TYPE_TASK => "exam.checkout_{$result}_message_subject_for_task",
            default => throw new \RuntimeException('Invalid type: '.$this->type)
        };
    }

    public function getMessageContentTransKey(string $result): string
    {
        return match ($this->type) {
            self::TYPE_EXAM => "exam.checkout_{$result}_message_content_for_exam",
            self::TYPE_TASK => "exam.checkout_{$result}_message_content_for_task",
            default => throw new \RuntimeException('Invalid type: '.$this->type)
        };
    }

    public function getPassResultTransKey(string $result): string
    {
        return match ($this->type) {
            self::TYPE_EXAM => "exam.result_{$result}_for_exam",
            self::TYPE_TASK => "exam.result_{$result}_for_task",
            default => throw new \RuntimeException('Invalid type: '.$this->type)
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
}
