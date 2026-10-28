<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Support\Locale;

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

        return implode(view('components.support.br')->render(), $arr);
    }
}
