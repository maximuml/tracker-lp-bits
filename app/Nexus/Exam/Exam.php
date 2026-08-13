<?php

namespace Nexus\Exam;

use App\Models\ExamUser;
use App\Repositories\ExamRepository;

class Exam
{
    public function getCurrent($uid): array
    {
        $examRep = new ExamRepository();
        $userExam = $examRep->getUserExamProgress($uid, ExamUser::STATUS_NORMAL);
        if (empty($userExam)) {
            return ['exam' => null, 'html' => ''];
        }
        /** @var \App\Models\Exam $exam */
        $exam = $userExam->exam;
        $row = [];
        $row[] = sprintf('%s：%s', \App\Support\Locale::trans('exam.name', [], null), $exam->name);
        $row[] = sprintf('%s：%s ~ %s', \App\Support\Locale::trans('exam.time_range', [], null), $userExam->begin, $userExam->end);
        foreach ($userExam->progress_formatted as $key => $index) {
            if (isset($index['checked']) && $index['checked']) {
                $row[] = sprintf(
                    '%s：%s, %s：%s, %s：%s, %s：%s',
                    \App\Support\Locale::trans('exam.index', [], null) . ($key + 1), \App\Support\Locale::trans('exam.index_text_' . $index['index'], [], null),
                    \App\Support\Locale::trans('exam.require_value', [], null), $index['require_value_formatted'],
                    \App\Support\Locale::trans('exam.current_value', [], null), $index['current_value_formatted'],
                    \App\Support\Locale::trans('exam.result', [], null),
                    $index['passed'] ? \App\Support\Locale::trans($exam->getPassResultTransKey("pass"), [], null) : \App\Support\Locale::trans($exam->getPassResultTransKey("not_pass"), [], null)
                );
            }
        }
        if ($exam->description) {
            $row[] = "\n" . $exam->description;
        }
        $html =  nl2br(implode("\n", $row));
        return compact('exam', 'html');
    }
}
