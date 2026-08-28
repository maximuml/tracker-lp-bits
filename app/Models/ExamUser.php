<?php

declare(strict_types=1);

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

use App\Enums\ExamUserIsDone;
use App\Enums\ExamUserStatus;
use App\Repositories\ExamRepository;
use App\Support\Locale;
use App\Support\Logger;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read Exam $exam
 * @property-read User $user
 */
class ExamUser extends NexusModel
{
    /** @var list<string> */
    protected $fillable = ['exam_id', 'uid', 'status', 'progress', 'begin', 'end', 'is_done'];

    /** @var bool */
    public $timestamps = true;

    /** @var array<int|string, mixed> */
    public static array $status = [
        ExamUserStatus::NORMAL->value => ['text' => 'Normal'],
        ExamUserStatus::FINISHED->value => ['text' => 'Finished'],
        ExamUserStatus::AVOIDED->value => ['text' => 'Avoided'],
    ];

    /** @var array<int|string, mixed> */
    public static array $isDoneInfo = [
        ExamUserIsDone::YES->value => ['text' => 'Yes'],
        ExamUserIsDone::NO->value => ['text' => 'No'],
    ];

    /** @var array<string, string> */
    protected $casts = [
        'progress' => 'json',
    ];

    public function getStatusTextAttribute(): string
    {
        return Locale::trans('exam-user.status.'.$this->status, [], null);
    }

    public function getIsDoneTextAttribute(): string
    {
        return self::$isDoneInfo[$this->is_done]['text'] ?? '';
    }

    /** @return  array<int|string, mixed> */
    public function getProgressFormattedAttribute(): array
    {
        $examRep = app(ExamRepository::class);

        return $examRep->getProgressFormatted($this->exam, (array) $this->progress);
    }

    /**
     * @param  mixed  $onlyKeyValue
     * @return array<int|string, mixed>
     */
    public static function listStatus($onlyKeyValue = false): array
    {
        $result = self::$status;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = Locale::trans('exam-user.status.'.$key, [], null);
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
            Logger::writeWithContext((string) sprintf('examUser: %s, begin from self: %s', $this->id, $begin), (string) 'info', (bool) false);

            return $begin;
        }

        $exam = $this->exam;
        $begin = $exam->getRawOriginal('begin');
        if ($begin) {
            Logger::writeWithContext((string) sprintf('examUser: %s, begin from exam(%s): %s', $this->id, $exam->id, $begin), (string) 'info', (bool) false);

            return $begin;
        }

        if ($exam->duration > 0) {
            Logger::writeWithContext((string) sprintf('examUser: %s, begin from self created_at(%s)', $this->id, $this->getRawOriginal('created_at')), (string) 'info', (bool) false);
            $createdAt = $this->created_at;

            return $createdAt instanceof Carbon ? $createdAt->toDateTimeString() : null;
        }

        return null;
    }

    /** @return  mixed */
    public function getEndAttribute()
    {
        $end = $this->getRawOriginal('end');
        if ($end) {
            Logger::writeWithContext((string) sprintf('examUser: %s, end from self: %s', $this->id, $end), (string) 'info', (bool) false);

            return $end;
        }

        $exam = $this->exam;
        $end = $exam->getRawOriginal('end');
        if ($end) {
            Logger::writeWithContext((string) sprintf('examUser: %s, end from exam(%s): %s', $this->id, $exam->id, $end), (string) 'info', (bool) false);

            return $end;
        }

        $duration = $exam->duration;
        if ($duration > 0) {
            Logger::writeWithContext((string) sprintf('examUser: %s, end from self created_at + exam(%s) created_at: %s + %s days', $this->id, $exam->id, $this->getRawOriginal('created_at'), $duration), (string) 'info', (bool) false);
            $createdAt = $this->created_at;

            return $createdAt instanceof Carbon ? $createdAt->addDays((int) $duration)->toDateTimeString() : null;
        }

        return null;
    }

    /** @return  BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    /** @return  HasMany<ExamProgress, $this> */
    public function progresses(): HasMany
    {
        return $this->hasMany(ExamProgress::class, 'exam_user_id');
    }
}
