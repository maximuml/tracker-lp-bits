<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\TorrentAlreadyExistsException;
use PHPUnit\Framework\TestCase;

final class TorrentAlreadyExistsExceptionTest extends TestCase
{
    public function test_it_exposes_the_existing_torrent_id(): void
    {
        $e = new TorrentAlreadyExistsException(123);

        $this->assertSame(123, $e->getTorrentId());
        $this->assertInstanceOf(\App\Exceptions\NexusException::class, $e);
    }
}
