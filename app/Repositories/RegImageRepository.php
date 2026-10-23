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
     * Answer string for the image captcha hash, or null when unknown.
     */
    public function findAnswerByHash(string $imagehash): ?string
    {
        return RegImage::query()
            ->where('imagehash', $imagehash)
            ->value('imagestring');
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
