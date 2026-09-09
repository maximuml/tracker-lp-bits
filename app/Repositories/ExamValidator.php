<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ExamFilterUser;
use App\Enums\ExamIndex;
use App\Models\Exam;
use App\Models\User;
use App\Support\Locale;
use Carbon\Carbon;

/**
 * Pure validation helper for exam definition parameters.
 *
 * Extracted from ExamRepository to separate validation concerns from
 * persistence. Performs no DB access directly — uses the Exam and User
 * models for in-memory validation only.
 */
class ExamValidator
{
    /**
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    public function formatParams(array $params): array
    {
        if (isset($params['begin']) && $params['begin'] == '') {
            $params['begin'] = null;
        }
        if (isset($params['end']) && $params['end'] == '') {
            $params['end'] = null;
        }
        $params['priority'] = intval($params['priority'] ?? 0);

        return $params;
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function checkIndexes(array $params, float $examDuration): bool
    {
        if (empty($params['indexes'])) {
            throw new \InvalidArgumentException('Require index.');
        }
        $validIndex = [];
        foreach ($params['indexes'] as $index) {
            if (isset($index['checked']) && ! $index['checked']) {
                continue;
            }
            if (isset($validIndex[$index['index']])) {
                throw new \InvalidArgumentException(Locale::trans('admin.resources.exam.index_duplicate', ['index' => Locale::trans("exam.index_text_{$index['index']}", [], null)], null));
            }
            if (isset($index['require_value']) && ! ctype_digit((string) $index['require_value'])) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid require value for index: %s.',
                    $index['index']
                ));
            }
            if ($index['index'] == ExamIndex::SEED_TIME_AVERAGE->value) {
                if ($index['require_value'] > $examDuration) {
                    throw new \InvalidArgumentException(Locale::trans('admin.resources.exam.index_seed_time_average_require_value_invalid', ['index_seed_time_average_require_value' => $index['require_value'], 'duration' => $examDuration], null));
                }
            }
            $validIndex[$index['index']] = $index;
        }
        if (empty($validIndex)) {
            throw new \InvalidArgumentException('Require valid index.');
        }

        return true;
    }

    /**
     * check if begin/end valid, if yes, return diff in hours, else throw InvalidArgumentException
     *
     * @param  array<int|string, mixed>  $params
     */
    public function checkBeginEnd(array $params): float
    {
        if (
            ! empty($params['begin']) && ! empty($params['end'])
            && empty($params['duration'])
            && empty($params['recurring'])
        ) {
            $begin = Carbon::parse($params['begin']);
            $end = Carbon::parse($params['end']);

            return round($begin->diffInHours($end, true));
        }
        if (
            empty($params['begin']) && empty($params['end'])
            && isset($params['duration']) && ctype_digit((string) $params['duration']) && $params['duration'] > 0
            && empty($params['recurring'])
        ) {
            // unit: day
            return round(floatval($params['duration']) * 24);
        }
        if (
            empty($params['begin']) && empty($params['end'])
            && empty($params['duration'])
            && ! empty($params['recurring'])
        ) {
            $exam = new Exam(['recurring' => $params['recurring']]);
            $now = Carbon::now();
            $begin = $exam->getRecurringBegin($now);
            $end = $exam->getRecurringEnd($now);

            return round($begin->diffInHours($end, true));
        }

        throw new \InvalidArgumentException(Locale::trans('exam.time_condition_invalid', [], null));
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function checkFilters(array $params)
    {
        $filters = $params['filters'];
        $hasValid = false;

        $filter = ExamFilterUser::USER_CLASS->value;
        if (! empty($filters[$filter])) {
            $hasValid = true;
            $diff = array_diff($filters[$filter], array_keys(User::$classes));
            if (! empty($diff)) {
                throw new \InvalidArgumentException(sprintf('Invalid user class: %s', json_encode($diff)));
            }
        }

        $filter = ExamFilterUser::DONATE->value;
        if (! empty($filters[$filter])) {
            $hasValid = true;
            $diff = array_diff($filters[$filter], array_keys(User::$donateStatus));
            if (! empty($diff)) {
                throw new \InvalidArgumentException(sprintf('Invalid user donate status: %s', json_encode($diff)));
            }
        }

        $filter = ExamFilterUser::REGISTER_TIME_RANGE->value;
        $begin = $filters[$filter][0] ?? null;
        $end = $filters[$filter][1] ?? null;
        if ($begin) {
            if (strtotime($begin)) {
                $hasValid = true;
            } else {
                throw new \InvalidArgumentException("Invalid user register time begin: $begin");
            }
        }
        if ($end) {
            if (strtotime($end)) {
                $hasValid = true;
            } else {
                throw new \InvalidArgumentException("Invalid user register time end: $end");
            }
        }
        if ($begin && $end && $begin > $end) {
            throw new \InvalidArgumentException('user register time begin must less than end');
        }

        $filter = ExamFilterUser::REGISTER_DAYS_RANGE->value;
        $begin = $filters[$filter][0] ?? null;
        $end = $filters[$filter][1] ?? null;
        if ($begin) {
            if (is_numeric($begin) && $begin >= 0) {
                $hasValid = true;
            } else {
                throw new \InvalidArgumentException("Invalid user register days begin: $begin");
            }
        }
        if ($end) {
            if (is_numeric($end) && $end >= 0) {
                $hasValid = true;
            } else {
                throw new \InvalidArgumentException("Invalid user register days end: $end");
            }
        }
        if ($begin && $end && $begin > $end) {
            throw new \InvalidArgumentException('user register days begin must less than end');
        }

        if (! $hasValid) {
            throw new \InvalidArgumentException('No valid filters');
        }

        return true;
    }
}
