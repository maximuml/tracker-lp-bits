<?php

declare(strict_types=1);

namespace App\Support;

/**
 * SSE Last-Event-ID codec for multi-channel feeds.
 *
 * A single numeric event id can only carry one sequence, but the
 * notification stream multiplexes five independent channels. The event
 * id therefore encodes the whole per-channel cursor map; on reconnect
 * the browser echoes it back and the stream resumes from the exact
 * delivered state instead of the stale connect-time query params.
 */
final class SseEventId
{
    /**
     * @param  array<string, int>  $cursors
     */
    public static function encode(array $cursors): string
    {
        return (string) json_encode(array_map('intval', $cursors));
    }

    /**
     * Overlay a Last-Event-ID value onto query-param cursors. A JSON
     * object wins per channel it covers (it is the freshest delivered
     * state); a bare integer is the legacy pm-only id and only advances
     * pm — the remaining channels keep their connect-time values.
     *
     * @param  array<string, int>  $cursors
     * @return array<string, int>
     */
    public static function applyTo(string $lastEventId, array $cursors): array
    {
        $lastEventId = trim($lastEventId);
        if ($lastEventId === '') {
            return $cursors;
        }

        $decoded = json_decode($lastEventId, true);
        if (is_array($decoded)) {
            foreach ($cursors as $channel => $value) {
                if (isset($decoded[$channel]) && is_numeric($decoded[$channel]) && (int) $decoded[$channel] >= 0) {
                    $cursors[$channel] = (int) $decoded[$channel];
                }
            }

            return $cursors;
        }

        if (ctype_digit($lastEventId)) {
            $cursors['pm'] = max((int) ($cursors['pm'] ?? 0), (int) $lastEventId);
        }

        return $cursors;
    }
}
