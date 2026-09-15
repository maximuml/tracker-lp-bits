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
use App\Support\Json;
use App\Support\LegacyDb;
use App\Support\Logger;

/**
 * Handles exam progress calculation, formatting, and bulk updates.
 *
 * Extracted from ExamRepository to reduce god-object surface area.
 */
class ExamProgressRepository extends BaseRepository
{
    public function __construct(
        private readonly ExamProgressCalculator $examProgressCalculator = new ExamProgressCalculator,
        private readonly ExamProgressLegacyRepository $legacyRepository = new ExamProgressLegacyRepository,
    ) {}

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
     * @param  array<int|string, mixed>  $progress
     * @param  mixed  $locale
     * @return array<int|string, mixed>
     */
    public function getProgressFormatted(Exam $exam, array $progress, $locale = null): array
    {
        return $this->examProgressCalculator->getProgressFormatted($exam, $progress, $locale);
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
        return $this->legacyRepository->addProgress($uid, $torrentId, $indexAndValue);
    }

    /**
     * @return array<int|string, mixed>|null
     *
     * @deprecated
     */
    public function calculateProgress(ExamUser $examUser, bool $allSum = false)
    {
        return $this->legacyRepository->calculateProgress($examUser, $allSum);
    }
}
