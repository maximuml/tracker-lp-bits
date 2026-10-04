<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\RegImage;

/**
 * regimages table — the registration-captcha image questions.
 */
final class RegImageRepository extends BaseRepository
{
    /**
     * Random image row older than the re-issue horizon.
     *
     * @return array{imagehash: string, question: string}|null
     */
    public function pickRandomBefore(string $before): ?array
    {
        /** @var array{imagehash: string, question: string}|null */
        return RegImage::query()
            ->where('dateline', '<', $before)
            ->inRandomOrder()
            ->first(['imagehash', 'question'])
            ?->toArray();
    }

    /** @return array{imagestring: string}|null */
    public function findAnswerByHash(string $imagehash): ?array
    {
        /** @var array{imagestring: string}|null */
        return RegImage::query()
            ->where('imagehash', $imagehash)
            ->first(['imagestring'])
            ?->toArray();
    }

    /** @param  array<string, mixed>  $attributes */
    public function insert(array $attributes): bool
    {
        return RegImage::query()->insert($attributes);
    }

    public function deleteByHash(string $imagehash): int
    {
        return RegImage::query()->where('imagehash', $imagehash)->delete();
    }

    /**
     * Dateline of the hash+answer pair — non-null only when the answer
     * matches.
     */
    public function findDateline(string $imagehash, string $imagestring): int|string|null
    {
        return RegImage::query()
            ->where('imagehash', $imagehash)
            ->where('imagestring', $imagestring)
            ->value('dateline');
    }
}
