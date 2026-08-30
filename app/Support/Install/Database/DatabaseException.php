<?php

declare(strict_types=1);

namespace App\Support\Install\Database;

class DatabaseException extends \Exception
{
    public function __construct($message, $query = '')
    {
        parent::__construct("$message [$query]");
    }
}
