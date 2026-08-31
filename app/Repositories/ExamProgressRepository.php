<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ExamIndex;
use App\Enums\ExamUserIsDone;
use App\Enums\ExamUserStatus;
use App\Exceptions\NexusException;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Env;
use App\Support\Format;
use App\Support\Json;
use App\Support\LegacyDb;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;

/**
 * Handles exam progress calculation, formatting, and bulk updates.
 *
 * Extracted from ExamRepository to reduce god-object surface area.
 */
class ExamProgressRepository extends BaseRepository
{
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
        $random = mt_rand(1, 100);
        Logger::writeWithContext((string) "probability: {$probability}, random: {$random}", (string) 'info', (bool) false);
        if ($random > $probability) {
            Logger::writeWithContext((string) "[SKIP_UPDATE_PROGRESS], random: {$random} > probability: {$probability}", (string) 'warning', (bool) false);

            return true;
        }
        $examProgress = $this->calculateProgress($examUser);
        if (! is_array($examProgress)) {
            $examProgress = [];
        }
        $examProgressFormatted = $this->getProgressFormatted($exam, $examProgress);
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
     * @param  mixed  $examUser
     */
    public function updateProgress($examUser, ?User $user = null): ExamUser|bool
    {
        $beginTimestamp = microtime(true);
        if (! $examUser instanceof ExamUser) {
            $uid = intval($examUser);
            $examUser = ExamUser::query()
                ->where('uid', $uid)
                ->where('status', ExamUserStatus::NORMAL->value)
                ->first();
            if (! $examUser instanceof ExamUser) {
                Logger::writeWithContext((string) "user: {$uid} no exam.", (string) 'info', (bool) false);

                return false;
            }
        }
        if ($examUser->status != ExamUserStatus::NORMAL->value) {
            Logger::writeWithContext((string) "examUser: {$examUser->id} status not normal, won't update progress.", (string) 'info', (bool) false);

            return false;
        }
        if ($examUser->is_done == ExamUserIsDone::YES->value) {
            /**
             * continue  update
             *
             * @since v1.7.0
             */
        }
        $exam = $examUser->exam;
        if (! $user instanceof User) {
            $user = $examUser->user()->select(['id', 'uploaded', 'downloaded', 'seedtime', 'leechtime', 'seedbonus', 'seed_points'])->first();
        }
        if (! $user instanceof User) {
            throw new \InvalidArgumentException("examUser: {$examUser->id} no user.");
        }
        if (! $exam instanceof Exam) {
            throw new \InvalidArgumentException("examUser: {$examUser->id} no exam.");
        }
        $attributes = [
            'exam_user_id' => $examUser->id,
            'uid' => $user->id,
            'exam_id' => $exam->id,
        ];
        $logPrefix = json_encode($attributes);
        $begin = $examUser->begin;
        if (empty($begin)) {
            throw new \InvalidArgumentException("$logPrefix, exam: {$examUser->id} no begin.");
        }
        $end = $examUser->end;
        if (empty($end)) {
            throw new \InvalidArgumentException("$logPrefix, exam: {$examUser->id} no end.");
        }
        $progressGrouped = $examUser->progresses->keyBy('index');
        $examUserProgressFieldData = [];
        $now = now();
        foreach ($exam->indexes as $index) {
            if (! isset($index['checked']) || ! $index['checked']) {
                continue;
            }
            if ($progressGrouped->isNotEmpty() && ! $progressGrouped->has($index['index'])) {
                continue;
            }
            if (! isset(Exam::$indexes[$index['index']])) {
                $msg = "Unknown index: {$index['index']}";
                Logger::writeWithContext((string) "{$logPrefix}, {$msg}", (string) 'error', (bool) false);
                throw new \RuntimeException($msg);
            }
            Logger::writeWithContext((string) ("{$logPrefix}, [HANDLING INDEX {$index['index']}]: ".json_encode($index)), (string) 'info', (bool) false);
            // First, collect data to store/update in table: exam_progress
            $attributes['index'] = $index['index'];
            $attributes['created_at'] = $now;
            $attributes['updated_at'] = $now;
            $attributes['value'] = $this->getProgressValue($user, $index['index'], $examUser);
            Logger::writeWithContext((string) ('[GET_TOTAL_VALUE]: '.$attributes['value']), (string) 'info', (bool) false);
            $newVersionProgress = ExamProgress::query()
                ->where('exam_user_id', $examUser->id)
                ->whereNull('torrent_id')
                ->where('index', $index['index'])
                ->orderBy('id', 'desc')
                ->first();
            Logger::writeWithContext((string) ('check newVersionProgress: '.LegacyDb::lastQuery(false, 'json').', exists: '.json_encode($newVersionProgress)), (string) 'info', (bool) false);
            if ($newVersionProgress) {
                // just need to do update the value
                if ($attributes['value'] != $newVersionProgress->value) {
                    $newVersionProgress->update(['value' => $attributes['value']]);
                    Logger::writeWithContext((string) ('newVersionProgress [EXISTS], doUpdate: '.LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);
                } else {
                    Logger::writeWithContext((string) 'newVersionProgress [EXISTS], no change....', (string) 'info', (bool) false);
                }
                $attributes['init_value'] = $newVersionProgress->init_value;
            } else {
                // do insert.
                $attributes['init_value'] = $attributes['value'];
                $attributes['torrent_id'] = null;
                ExamProgress::query()->insert($attributes);
                Logger::writeWithContext((string) ('newVersionProgress [NOT EXISTS], doInsert with: '.json_encode($attributes)), (string) 'info', (bool) false);
            }

            // Second, update exam_user.progress
            if ($index['index'] == ExamIndex::SEED_TIME_AVERAGE->value) {
                $torrentCountsRes = Snatch::query()
                    ->where('userid', $user->id)
                    ->where('last_action', '>=', $begin)
                    ->where('last_action', '<=', $end)
                    ->selectRaw('count(distinct(torrentid)) as counts')
                    ->first();
                Logger::writeWithContext((string) ("special index: {$index['index']}, get torrent count by: ".LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);
                // if just seeding, no download torrent, counts = 1
                if ($torrentCountsRes && $torrentCountsRes->counts > 0) {
                    $torrentCounts = $torrentCountsRes->counts;
                    Logger::writeWithContext((string) "torrent count: {$torrentCounts}", (string) 'info', (bool) false);
                } else {
                    $torrentCounts = 1;
                    Logger::writeWithContext((string) 'torrent count is 0, use 1', (string) 'info', (bool) false);
                }
                $valueStr = sprintf('%d', $attributes['value']);
                $initValueStr = sprintf('%d', $attributes['init_value']);
                $examUserProgressFieldData[$index['index']] = bcdiv(bcsub($valueStr, $initValueStr), (string) $torrentCounts);
                Logger::writeWithContext((string) sprintf('torrentCounts > 0, examUserProgress: (total(%s) - init_value(%s)) / %s = %s', $attributes['value'], $attributes['init_value'], $torrentCounts, $examUserProgressFieldData[$index['index']]), (string) 'info', (bool) false);
            } else {
                $examUserProgressFieldData[$index['index']] = bcsub(sprintf('%d', $attributes['value']), sprintf('%d', $attributes['init_value']));
                Logger::writeWithContext((string) sprintf("normal index: {$index['index']}, examUserProgress: total(%s) - init_value(%s) = %s", $attributes['value'], $attributes['init_value'], $examUserProgressFieldData[$index['index']]), (string) 'info', (bool) false);
            }
        }
        $examProgressFormatted = $this->getProgressFormatted($exam, $examUserProgressFieldData);
        $examNotPassed = array_filter($examProgressFormatted, function ($item) {
            return ! $item['passed'];
        });

        $update = [
            'progress' => $examUserProgressFieldData,
            'is_done' => count($examNotPassed) ? ExamUserIsDone::NO->value : ExamUserIsDone::YES->value,
        ];
        $result = $examUser->update($update);
        Logger::writeWithContext((string) sprintf('[UPDATE_PROGRESS] %s, result: %s, cost time: %s sec', json_encode($update), var_export($result, true), sprintf('%.3f', microtime(true) - $beginTimestamp)), (string) 'info', (bool) false);
        $examUser->progress_formatted = $examProgressFormatted;

        return $examUser;
    }

    /**
     * @return mixed
     */
    private function getProgressValue(User $user, int $index, ExamUser $examUser)
    {
        if ($index == ExamIndex::UPLOADED->value) {
            return $user->uploaded;
        }
        if ($index == ExamIndex::DOWNLOADED->value) {
            return $user->downloaded;
        }
        if ($index == ExamIndex::SEED_BONUS->value) {
            return $user->seedbonus;
        }
        if ($index == ExamIndex::SEED_TIME_AVERAGE->value) {
            return $user->seedtime;
        }
        if ($index == ExamIndex::SEED_POINTS->value) {
            return $user->seed_points;
        }
        if ($index == ExamIndex::UPLOAD_TORRENT_COUNT->value) {
            return Torrent::query()->where('owner', $user->id)->where('added', '>=', $examUser->created_at)->normal()->count();
        }
        throw new \InvalidArgumentException("Invalid index: $index");
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $status
     * @return mixed|null
     */
    public function getUserExamProgress($uid, $status = null)
    {
        $logPrefix = "uid: $uid";
        $query = ExamUser::query()->where('uid', $uid)->orderBy('exam_id', 'desc');
        if ($status !== null) {
            $query->where('status', $status);
        }
        $examUsers = $query->get();
        if ($examUsers->isEmpty()) {
            Logger::writeWithContext((string) ("{$logPrefix}, no examUser, query: ".LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);

            return null;
        }
        if ($examUsers->count() > 1) {
            Logger::writeWithContext((string) "{$logPrefix}, user exam more than 1.", (string) 'warning', (bool) false);
        }
        $examUser = $examUsers->first();
        $logPrefix .= ', examUser: '.$examUser->id;
        try {
            $updateResult = $this->updateProgress($examUser);
            if ($updateResult) {
                Logger::writeWithContext((string) "{$logPrefix}, [UPDATE_PROGRESS_SUCCESS_RETURN_DIRECTLY]", (string) 'info', (bool) false);

                return $updateResult;
            } else {
                Logger::writeWithContext((string) "{$logPrefix}, [UPDATE_PROGRESS_FAIL]", (string) 'info', (bool) false);
            }
        } catch (\Exception $exception) {
            Logger::writeWithContext((string) ("{$logPrefix}, [UPDATE_PROGRESS_FAIL]: ".$exception->getMessage()), (string) 'error', (bool) false);
        }
        $exam = $examUser->exam;
        $progress = $examUser->progress;
        Logger::writeWithContext((string) ("{$logPrefix}, progress: ".Json::encode($progress)), (string) 'info', (bool) false);
        $examUser->progress = $progress;
        $examUser->progress_formatted = $this->getProgressFormatted($exam, (array) $progress);

        return $examUser;
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

    /**
     * @param  array<int|string, mixed>  $progress
     * @param  mixed  $locale
     * @return mixed
     */
    public function getProgressFormatted(Exam $exam, array $progress, $locale = null)
    {
        $result = [];
        foreach ($exam->indexes as $key => $index) {
            if (! isset($index['checked']) || ! $index['checked']) {
                continue;
            }
            if (! isset($progress[$index['index']])) {
                continue;
            }
            $currentValue = (float) ($progress[$index['index']] ?? 0);
            $requireValue = $index['require_value'];
            $unit = Exam::$indexes[$index['index']]['unit'] ?? '';
            switch ($index['index']) {
                case ExamIndex::UPLOADED->value:
                case ExamIndex::DOWNLOADED->value:
                    $currentValueFormatted = Format::size($currentValue);
                    $requireValueAtomic = $requireValue * 1024 * 1024 * 1024;
                    break;
                case ExamIndex::SEED_TIME_AVERAGE->value:
                    $currentValueFormatted = number_format($currentValue / 3600, 2)." $unit";
                    $requireValueAtomic = $requireValue * 3600;
                    break;
                default:
                    $currentValueFormatted = $currentValue;
                    $requireValueAtomic = $requireValue;
            }
            $index['name'] = Exam::$indexes[$index['index']]['name'] ?? '';
            $index['index_formatted'] = Locale::trans('exam.index_text_'.$index['index'], [], null);
            $index['require_value_formatted'] = "$requireValue $unit";
            $index['current_value'] = $currentValue;
            $index['current_value_formatted'] = $currentValueFormatted;
            $index['passed'] = $currentValue >= $requireValueAtomic;
            $index['index_result'] = $index['passed'] ? Locale::trans($exam->getPassResultTransKey('pass'), [], null) : Locale::trans($exam->getPassResultTransKey('not_pass'), [], null);
            $result[] = $index;
        }

        return $result;
    }

    /** @return  array<int|string, mixed> */
    public function updateProgressBulk(): array
    {
        $query = ExamUser::query()
            ->where('status', ExamUserStatus::NORMAL->value)
            ->where('is_done', ExamUserIsDone::NO->value);
        $page = 1;
        $size = 1000;
        $total = $success = 0;
        while (true) {
            $logPrefix = "[UPDATE_EXAM_PROGRESS], page: $page, size: $size";
            $rows = $query->forPage($page, $size)->get();
            $count = $rows->count();
            $total += $count;
            Logger::writeWithContext((string) ("{$logPrefix}, ".LegacyDb::lastQuery(false, 'json').", count: {$count}"), (string) 'info', (bool) false);
            if ($rows->isEmpty()) {
                Logger::writeWithContext((string) "{$logPrefix}, no more data...", (string) 'info', (bool) false);
                break;
            }
            foreach ($rows as $row) {
                $result = $this->updateProgress($row);
                Logger::writeWithContext((string) ("{$logPrefix}, examUser: ".$row->toJson().', result type: '.gettype($result)), (string) 'info', (bool) false);
                if ($result) {
                    $success += 1;
                }
            }
            $page++;
        }
        $result = compact('total', 'success');
        Logger::writeWithContext((string) ("{$logPrefix}, result: ".json_encode($result)), (string) 'info', (bool) false);

        return $result;
    }
}
