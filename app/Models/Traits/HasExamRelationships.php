<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\ExamUserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Eloquent relationships for the Exam model.
 */
trait HasExamRelationships
{
    /** @return BelongsToMany<User, $this> */
    public function users()
    {
        return $this->belongsToMany(User::class, 'exam_users', 'exam_id', 'uid');
    }

    /** @return mixed */
    public function onGoingUsers()
    {
        return $this->users()->wherePivot('status', ExamUserStatus::NORMAL->value);
    }
}
