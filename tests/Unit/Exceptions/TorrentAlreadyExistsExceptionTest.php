<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\NexusException;
use App\Exceptions\TorrentAlreadyExistsException;
use PHPUnit\Framework\TestCase;

final class TorrentAlreadyExistsExceptionTest extends TestCase
{
    public function test_it_exposes_the_existing_torrent_id(): void
    {
        $e = new TorrentAlreadyExistsException(123, 'Torrent already exists');

        $this->assertSame(123, $e->getTorrentId());
        $this->assertInstanceOf(NexusException::class, $e);
        $this->assertSame('Torrent already exists', $e->getMessage());
    }
}
