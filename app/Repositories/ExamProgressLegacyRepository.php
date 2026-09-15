<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ExamIndex;
use App\Enums\ExamUserIsDone;
use App\Enums\ExamUserStatus;
use App\Exceptions\NexusException;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Env;
use App\Support\Json;
use App\Support\LegacyDb;
use App\Support\Logger;
use Carbon\Carbon;

/**
 * Deprecated exam-progress code paths retained for the old torrent-level
 * progress writes (addProgress) and the pre-recompute calculateProgress.
 *
 * Extracted from ExamProgressRepository to keep both classes under the
 * 400-line ratchet.
 */
class ExamProgressLegacyRepository
{
    public function __construct(
        private readonly ExamProgressCalculator $examProgressCalculator = new ExamProgressCalculator,
    ) {}

    /**
     * @param  array<int|string, mixed>  $indexAndValue
     * @return bool
     *
     * @deprecated old version used
     *
     * @throws NexusException
     */
    public function addProgress(int $uid, int $torrentId, array $indexAndValue)
    {
        $logPrefix = "uid: $uid, torrentId: $torrentId, indexAndValue: ".json_encode($indexAndValue);
        Logger::writeWithContext((string) $logPrefix, (string) 'info', (bool) false);

        $user = User::query()->findOrFail($uid);
        $user->checkIsNormal();

        $now = Carbon::now()->toDateTimeString();
        $examUser = $user->exams()->where('status', ExamUserStatus::NORMAL->value)->orderBy('id', 'desc')->first();
        if (! $examUser) {
            Logger::writeWithContext((string) ('no exam is on the way, '.LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);

            return false;
        }
        $exam = $examUser->exam;
        if (! $exam) {
            throw new NexusException("exam: {$examUser->exam_id} not exists.");
        }
        $begin = $examUser->begin;
        $end = $examUser->end;
        if (! $begin || ! $end) {
            Logger::writeWithContext((string) sprintf('no begin or end, examUser: %s', $examUser->toJson()), (string) 'info', (bool) false);

            return false;
        }
        if ($now < $begin || $now > $end) {
            Logger::writeWithContext((string) sprintf('now: %s, not in exam time range: %s ~ %s', $now, $begin, $end), (string) 'info', (bool) false);

            return false;
        }
        $indexes = collect($exam->indexes)->keyBy('index');
        Logger::writeWithContext((string) ('examUser: '.$examUser->toJson().', indexes: '.$indexes->toJson()), (string) 'info', (bool) false);

        if (! isset($indexAndValue[ExamIndex::SEED_BONUS->value])) {
            // seed bonus is relative to user all torrents, not single one, torrentId = 0
            $torrentFields = ['id', 'visible', 'banned'];
            $torrent = Torrent::query()->findOrFail($torrentId, $torrentFields);
            $torrent->checkIsNormal($torrentFields);
        }

        $insert = [];
        foreach ($indexAndValue as $indexId => $value) {
            if (! $indexes->has($indexId)) {
                Logger::writeWithContext((string) sprintf('Exam: %s does not has index: %s.', $exam->id, $indexId), (string) 'info', (bool) false);

                continue;
            }
            $indexInfo = $indexes->get($indexId);
            if (! isset($indexInfo['checked']) || ! $indexInfo['checked']) {
                Logger::writeWithContext((string) sprintf('Exam: %s index: %s is not checked.', $exam->id, $indexId), (string) 'info', (bool) false);

                continue;
            }
            $insert[] = [
                'exam_user_id' => $examUser->id,
                'uid' => $user->id,
                'exam_id' => $exam->id,
                'torrent_id' => $torrentId,
                'index' => $indexId,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (empty($insert)) {
            Logger::writeWithContext((string) 'no progress to insert.', (string) 'info', (bool) false);

            return false;
        }
        ExamProgress::query()->insert($insert);
        Logger::writeWithContext((string) ('[addProgress] '.Json::encode($insert)), (string) 'info', (bool) false);

        /**
         * Updating progress is more performance intensive and will only be done with a certain probability
         */
        $probability = (int) Env::get('EXAM_PROGRESS_UPDATE_PROBABILITY', 60);
        $random = random_int(1, 100);
        Logger::writeWithContext((string) "probability: {$probability}, random: {$random}", (string) 'info', (bool) false);
        if ($random > $probability) {
            Logger::writeWithContext((string) "[SKIP_UPDATE_PROGRESS], random: {$random} > probability: {$probability}", (string) 'warning', (bool) false);

            return true;
        }
        $examProgress = $this->calculateProgress($examUser);
        if (! is_array($examProgress)) {
            $examProgress = [];
        }
        $examProgressFormatted = $this->examProgressCalculator->getProgressFormatted($exam, $examProgress);
        $examNotPassed = array_filter($examProgressFormatted, function ($item) {
            return ! $item['passed'];
        });
        $update = [
            'progress' => $examProgress,
            'is_done' => count($examNotPassed) ? ExamUserIsDone::NO->value : ExamUserIsDone::YES->value,
        ];
        Logger::writeWithContext((string) ('[updateProgress] '.Json::encode($update)), (string) 'info', (bool) false);
        $examUser->update($update);

        return true;
    }

    /**
     * @return array<int|string, mixed>|null
     *
     * @deprecated
     */
    public function calculateProgress(ExamUser $examUser, bool $allSum = false)
    {
        $logPrefix = 'examUser: '.$examUser->id;
        $begin = $examUser->begin;
        $end = $examUser->end;
        if (! $begin) {
            Logger::writeWithContext((string) "{$logPrefix}, no begin", (string) 'info', (bool) false);

            return null;
        }
        if (! $end) {
            Logger::writeWithContext((string) "{$logPrefix}, no end", (string) 'info', (bool) false);

            return null;
        }
        $progressSum = $examUser->progresses()
            ->where('created_at', '>=', $begin)
            ->where('created_at', '<=', $end)
            ->selectRaw('`index`, sum(`value`) as sum')
            ->groupBy(['index'])
            ->get()
            ->pluck('sum', 'index')
            ->toArray();
        $logPrefix .= ', progressSum raw: '.json_encode($progressSum).', query: '.LegacyDb::lastQuery(false, 'json');
        if ($allSum) {
            Logger::writeWithContext((string) $logPrefix, (string) 'info', (bool) false);

            return $progressSum;
        }

        $index = ExamIndex::SEED_TIME_AVERAGE->value;
        if (isset($progressSum[$index])) {
            $torrentCountRow = $examUser->progresses()
                ->where('index', $index)
                ->where('torrent_id', '>=', 0)
                ->selectRaw('count(distinct(torrent_id)) as torrent_count')
                ->first();
            $torrentCount = $torrentCountRow instanceof ExamProgress ? (int) $torrentCountRow->torrent_count : 0;
            $progressSum[$index] = intval($progressSum[$index] / $torrentCount);
            $logPrefix .= ", index: INDEX_SEED_TIME_AVERAGE, get torrent count: $torrentCount, from query: ".LegacyDb::lastQuery(false, 'json');
        }

        Logger::writeWithContext((string) ("{$logPrefix}, final progressSum: ".json_encode($progressSum)), (string) 'info', (bool) false);

        return $progressSum;

    }
}
