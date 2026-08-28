<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\ExamFilterUser;
use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\User;
use App\Support\Locale;
use Carbon\Carbon;

/**
 * Eloquent attribute accessors for the Exam model.
 */
trait HasExamAccessors
{
    /** @return mixed */
    public function getTypeTextAttribute()
    {
        return Exam::listTypeOptions()[$this->type] ?? '';
    }

    protected function getRecurringTextAttribute(): string
    {
        $options = Exam::listRecurringOptions();

        return $options[(string) $this->recurring] ?? '';
    }

    public function getStatusTextAttribute(): string
    {
        return $this->status == ExamStatus::ENABLED->value ? Locale::trans('label.enabled', [], null) : Locale::trans('label.disabled', [], null);
    }

    public function getIsDiscoveredTextAttribute(): string
    {
        return Exam::$discovers[$this->is_discovered]['text'] ?? '';
    }

    public function getDurationTextAttribute(): string
    {
        if ($this->duration > 0) {
            return $this->duration.' Days';
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
                    Locale::trans("exam.index_text_{$index['index']}", [], null),
                    $index['require_value'],
                    Exam::$indexes[$index['index']]['unit'] ?? ''
                );
            }
        }

        return implode('<br/>', $arr);
    }

    public function getFilterFormattedAttribute(): string
    {
        $currentFilters = $this->filters;
        $arr = [];
        $filter = ExamFilterUser::USER_CLASS->value;
        if (! empty($currentFilters[$filter])) {
            $classes = collect(User::$classes)->only($currentFilters[$filter]);
            $arr[] = sprintf(
                '%s: %s',
                Locale::trans("exam.filters.{$filter}", [], null), $classes->map(fn ($value, $key) => User::getClassText($key))->implode(', ')
            );
        }

        $filter = ExamFilterUser::REGISTER_TIME_RANGE->value;
        if (! empty($currentFilters[$filter])) {
            $range = $currentFilters[$filter];
            if (! empty($range[0]) || ! empty($range[1])) {
                $arr[] = sprintf(
                    '%s: <br/>%s ~ %s',
                    Locale::trans("exam.filters.{$filter}", [], null),
                    $range[0] ? Carbon::parse($range[0])->toDateTimeString() : '--',
                    $range[1] ? Carbon::parse($range[1])->toDateTimeString() : '--'
                );
            }
        }

        $filter = ExamFilterUser::REGISTER_DAYS_RANGE->value;
        if (! empty($currentFilters[$filter])) {
            $range = $currentFilters[$filter];
            if (! empty($range[0]) || ! empty($range[1])) {
                $arr[] = sprintf(
                    '%s: %s ~ %s',
                    Locale::trans("exam.filters.{$filter}", [], null),
                    $range[0] ?? '--',
                    $range[1] ?? '--'
                );
            }
        }

        $filter = ExamFilterUser::DONATE->value;
        if (! empty($currentFilters[$filter])) {
            $donateStatus = collect(User::$donateStatus)->only($currentFilters[$filter]);
            $arr[] = sprintf('%s: %s', Locale::trans("exam.filters.{$filter}", [], null), $donateStatus->pluck('text')->implode(', '));
        }

        return implode('<br/>', $arr);
    }
}
