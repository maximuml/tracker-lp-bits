<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Writes Server-Sent Events frames to the output buffer.
 */
final class SseWriter
{
    public function event(string $name, string $data, int|string|null $id = null): void
    {
        if ($id !== null) {
            echo 'id: '.$id."\n";
        }
        echo 'event: '.$name."\n";
        echo 'data: '.$data."\n\n";
        $this->flush();
    }

    public function ping(): void
    {
        $this->event('ping', '{}');
    }

    public function flush(): void
    {
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}
