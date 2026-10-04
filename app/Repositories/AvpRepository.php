<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Avp;

/**
 * Read/write helpers over the `avps` argument/value table (cleanup run
 * bookkeeping: last-run timestamps and runner claims).
 */
final class AvpRepository extends BaseRepository
{
    public function getUIntValue(string $arg): int
    {
        return (int) Avp::query()->where('arg', $arg)->value('value_u');
    }

    public function insertOrIgnore(string $arg, int $valueU): int
    {
        return Avp::query()->insertOrIgnore(['arg' => $arg, 'value_s' => '', 'value_u' => $valueU]);
    }

    /**
     * Claim the run: bump value_u only if it still equals $expectedTs.
     * Returns affected rows — 0 means another runner claimed it first.
     */
    public function claimIfValueMatches(string $arg, int $expectedTs, int $newTs): int
    {
        return Avp::query()
            ->where('arg', $arg)
            ->where('value_u', $expectedTs)
            ->update(['value_u' => $newTs]);
    }

    public function updateOrInsert(string $arg, int $valueU): bool
    {
        return Avp::query()->toBase()->updateOrInsert(['arg' => $arg], ['value_s' => '', 'value_u' => $valueU]);
    }
}
