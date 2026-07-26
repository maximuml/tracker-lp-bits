<?php

/**
 * @property int $id
 * @property int $uid
 * @property int $exam_id
 * @property int $status
 * @property string|null $begin
 * @property string|null $end
 * @property string|null $progress
 * @property int $is_done
 * @property string|null $created_at
 * @property string|null $updated_at
 */
namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use App\Repositories\ExamRepository;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Exam $exam
 * @property-read User $user
 */
class ExamUser extends NexusModel
{
    /** @var  list<string> */
    protected $fillable = ['exam_id', 'uid', 'status', 'progress', 'begin', 'end', 'is_done'];

    /** @var  bool */
    public $timestamps = true;

    const STATUS_NORMAL = 0;
    const STATUS_FINISHED = 1;
    const STATUS_AVOIDED = -1;

    /** @var  array<int|string, mixed> */
    public static array $status = [
        self::STATUS_NORMAL => ['text' => 'Normal'],
        self::STATUS_FINISHED => ['text' => 'Finished'],
        self::STATUS_AVOIDED => ['text' => 'Avoided'],
    ];

    const IS_DONE_YES = 1;
    const IS_DONE_NO = 0;

    /** @var  array<int|string, mixed> */
    public static array $isDoneInfo = [
        self::IS_DONE_YES => ['text' => 'Yes'],
        self::IS_DONE_NO => ['text' => 'No'],
    ];


    /** @var  array<string, string> */
    protected $casts = [
        'progress' => 'json'
    ];

    public function getStatusTextAttribute(): string
    {
        return nexus_trans('exam-user.status.' . $this->status);
    }

    public function getIsDoneTextAttribute(): string
    {
        return self::$isDoneInfo[$this->is_done]['text'] ?? '';
    }

    /** @return  array<int|string, mixed> */
    public function getProgressFormattedAttribute(): array
    {
        $examRep = new ExamRepository();
        return $examRep->getProgressFormatted($this->exam, $this->progress);
    }

    /**
     * @param  mixed  $onlyKeyValue
     * @return  array<int|string, mixed>
     */
    public static function listStatus($onlyKeyValue = false): array
    {
        $result = self::$status;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = nexus_trans('exam-user.status.' . $key);
            $value['text'] = $text;
            $keyValues[$key] = $text;
        }
        if ($onlyKeyValue) {
            return $keyValues;
        }
        return $result;
    }

    /** @return  mixed */
    public function getBeginAttribute()
    {
        $begin = $this->getRawOriginal('begin');
        if ($begin) {
            do_log(sprintf('examUser: %s, begin from self: %s', $this->id, $begin));
            return $begin;
        }

        $exam = $this->exam;
        $begin = $exam->getRawOriginal('begin');
        if ($begin) {
            do_log(sprintf('examUser: %s, begin from exam(%s): %s', $this->id, $exam->id, $begin));
            return $begin;
        }

        if ($exam->duration > 0) {
            do_log(sprintf('examUser: %s, begin from self created_at(%s)', $this->id, $this->getRawOriginal('created_at')));
            return $this->created_at->toDateTimeString();
        }
        return null;
    }

    /** @return  mixed */
    public function getEndAttribute()
    {
        $end = $this->getRawOriginal('end');
        if ($end) {
            do_log(sprintf('examUser: %s, end from self: %s', $this->id, $end));
            return $end;
        }

        $exam = $this->exam;
        $end = $exam->getRawOriginal('end');
        if ($end) {
            do_log(sprintf('examUser: %s, end from exam(%s): %s', $this->id, $exam->id, $end));
            return $end;
        }

        $duration = $exam->duration;
        if ($duration > 0) {
            do_log(sprintf('examUser: %s, end from self created_at + exam(%s) created_at: %s + %s days', $this->id, $exam->id, $this->getRawOriginal('created_at'), $duration));
            return $this->created_at->addDays((int)$duration)->toDateTimeString();
        }
        return null;
    }


    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\HasMany<ExamProgress, $this> */
    public function progresses(): HasMany
    {
        return $this->hasMany(ExamProgress::class, 'exam_user_id');
    }


}
