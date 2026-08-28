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

use App\Enums\ExamDiscovered;
use App\Enums\ExamFilterUser;
use App\Enums\ExamIndex;
use App\Enums\ExamRecurring;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
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

    /** @var array<int|string, mixed> */
    public static $status = [
        ExamStatus::ENABLED->value => ['text' => 'Enabled'],
        ExamStatus::DISABLED->value => ['text' => 'Disabled'],
    ];

    /** @var array<int|string, mixed> */
    public static $discovers = [
        ExamDiscovered::NO->value => ['text' => 'No'],
        ExamDiscovered::YES->value => ['text' => 'Yes'],
    ];

    /** @var array<int|string, mixed> */
    public static array $indexes = [
        ExamIndex::UPLOADED->value => ['name' => 'Uploaded', 'unit' => 'GB', 'source_user_field' => 'uploaded'],
        ExamIndex::DOWNLOADED->value => ['name' => 'Downloaded', 'unit' => 'GB', 'source_user_field' => 'downloaded'],
        ExamIndex::SEED_TIME_AVERAGE->value => ['name' => 'Seed time average', 'unit' => 'Hour', 'source_user_field' => 'seedtime'],
        ExamIndex::SEED_BONUS->value => ['name' => 'Bonus', 'unit' => '', 'source_user_field' => 'seedbonus'],
        ExamIndex::SEED_POINTS->value => ['name' => 'Seed points', 'unit' => '', 'source_user_field' => ''],
        ExamIndex::UPLOAD_TORRENT_COUNT->value => ['name' => 'Upload torrent', 'unit' => '', 'source_user_field' => ''],
    ];

    /** @var array<int|string, mixed> */
    public static $filters = [
        ExamFilterUser::USER_CLASS->value => ['name' => 'User class'],
        ExamFilterUser::REGISTER_TIME_RANGE->value => ['name' => 'User register time range'],
        ExamFilterUser::DONATE->value => ['name' => 'User donated'],
        ExamFilterUser::REGISTER_DAYS_RANGE->value => ['name' => 'User register days range'],
    ];

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
            ExamRecurring::DAILY->value => Locale::trans('exam.recurring_daily', [], null),
            ExamRecurring::WEEKLY->value => Locale::trans('exam.recurring_weekly', [], null),
            ExamRecurring::MONTHLY->value => Locale::trans('exam.recurring_monthly', [], null),
        ];
    }

    /** @return  array<int|string, mixed> */
    public static function listTypeOptions(): array
    {
        return [
            ExamType::EXAM->value => Locale::trans('exam.type_exam', [], null),
            ExamType::TASK->value => Locale::trans('exam.type_task', [], null),
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
        if ($recurring == ExamRecurring::WEEKLY->value) {
            return $time->startOfWeek();
        } elseif ($recurring == ExamRecurring::MONTHLY->value) {
            return $time->startOfMonth();
        } elseif ($recurring == ExamRecurring::DAILY->value) {
            return $time->startOfDay();
        }
        throw new \RuntimeException("Invalid recurring: $recurring");
    }

    public function getRecurringEnd(Carbon $time): Carbon
    {
        $time = $time->copy();
        $recurring = $this->recurring;
        if ($recurring == ExamRecurring::WEEKLY->value) {
            return $time->endOfWeek();
        } elseif ($recurring == ExamRecurring::MONTHLY->value) {
            return $time->endOfMonth();
        } elseif ($recurring == ExamRecurring::DAILY->value) {
            return $time->endOfDay();
        }
        throw new \RuntimeException("Invalid recurring: $recurring");
    }

    public function getMessageSubjectTransKey(string $result): string
    {
        return match ($this->type) {
            ExamType::EXAM->value => "exam.checkout_{$result}_message_subject_for_exam",
            ExamType::TASK->value => "exam.checkout_{$result}_message_subject_for_task",
            default => throw new \RuntimeException('Invalid type: '.$this->type)
        };
    }

    public function getMessageContentTransKey(string $result): string
    {
        return match ($this->type) {
            ExamType::EXAM->value => "exam.checkout_{$result}_message_content_for_exam",
            ExamType::TASK->value => "exam.checkout_{$result}_message_content_for_task",
            default => throw new \RuntimeException('Invalid type: '.$this->type)
        };
    }

    public function getPassResultTransKey(string $result): string
    {
        return match ($this->type) {
            ExamType::EXAM->value => "exam.result_{$result}_for_exam",
            ExamType::TASK->value => "exam.result_{$result}_for_task",
            default => throw new \RuntimeException('Invalid type: '.$this->type)
        };
    }

    public function isTypeExam(): bool
    {
        return $this->type == ExamType::EXAM->value;
    }

    public function isTypeTask(): bool
    {
        return $this->type == ExamType::TASK->value;
    }
}
