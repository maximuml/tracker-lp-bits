<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Legacy progress-bar helper extracted from `include/functions.php`.
 *
 * Backs `get_percent_completed_image()`.
 */
final class Progress
{
    /**
     * Build the HTML progress-bar image for a percentage value.
     *
     * Mirrors `get_percent_completed_image()`. Widths go through the
     * presentational `width` attribute (inline `style` is CSP-blocked).
     */
    public static function percentImage(int|float|string $p): string
    {
        $p = (float) $p;
        $maxpx = 45;

        $segments = [];
        if ($p == 0) {
            $segments[] = ['class' => 'progbarrest', 'width' => $maxpx];
        } elseif ($p == 100) {
            $segments[] = ['class' => 'progbargreen', 'width' => $maxpx];
        } elseif ($p >= 1 && $p <= 99) {
            $fill = match (true) {
                $p <= 30 => 'progbarred',
                $p <= 65 => 'progbaryellow',
                default => 'progbargreen',
            };
            $segments[] = ['class' => $fill, 'width' => (int) round($p * ($maxpx / 100))];
            $segments[] = ['class' => 'progbarrest', 'width' => (int) round((100 - $p) * ($maxpx / 100))];
        }

        return trim(view('support._progress-bar', ['segments' => $segments])->render());
    }
}
