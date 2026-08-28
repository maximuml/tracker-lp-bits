<?php

declare(strict_types=1);

namespace App\Exceptions;

class TrackerException extends NexusException
{
    public static function failure(string $message): self
    {
        return new self($message);
    }
}
