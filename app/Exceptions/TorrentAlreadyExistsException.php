<?php

namespace App\Exceptions;

use App\Support\Locale;

class TorrentAlreadyExistsException extends NexusException
{
    private int $torrentId;

    public function __construct(int $torrentId, ?string $message = null)
    {
        if ($message === null) {
            $message = Locale::trans('upload.torrent_existed', ['id' => $torrentId], null);
        }
        parent::__construct($message, 0, null);
        $this->torrentId = $torrentId;
    }

    public function getTorrentId(): int
    {
        return $this->torrentId;
    }
}
