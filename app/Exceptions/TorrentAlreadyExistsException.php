<?php

namespace App\Exceptions;

class TorrentAlreadyExistsException extends NexusException
{
    private int $torrentId;

    public function __construct(int $torrentId)
    {
        parent::__construct('torrent.already_exists', 0, null);
        $this->torrentId = $torrentId;
    }

    public function getTorrentId(): int
    {
        return $this->torrentId;
    }
}
