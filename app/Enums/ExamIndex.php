<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for exam index/metric type.
 *
 * Mirrors the integer constants from App\Models\Exam:
 *   INDEX_UPLOADED (1), INDEX_SEED_TIME_AVERAGE (2), INDEX_DOWNLOADED (3),
 *   INDEX_SEED_BONUS (4), INDEX_SEED_POINTS (5), INDEX_UPLOAD_TORRENT_COUNT (6).
 */
enum ExamIndex: int
{
    case UPLOADED = 1;
    case SEED_TIME_AVERAGE = 2;
    case DOWNLOADED = 3;
    case SEED_BONUS = 4;
    case SEED_POINTS = 5;
    case UPLOAD_TORRENT_COUNT = 6;

    public function label(): string
    {
        return match ($this) {
            self::UPLOADED => 'Uploaded',
            self::SEED_TIME_AVERAGE => 'Seed time average',
            self::DOWNLOADED => 'Downloaded',
            self::SEED_BONUS => 'Seed bonus',
            self::SEED_POINTS => 'Seed points',
            self::UPLOAD_TORRENT_COUNT => 'Upload torrent count',
        };
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::UPLOADED;
    }
}
