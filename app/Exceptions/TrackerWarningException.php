<?php

namespace App\Exceptions;

final class TrackerWarningException extends \Exception
{
    private array $response;

    public function __construct(string $message, array $baseResponse, int $interval = 7200)
    {
        parent::__construct($message);

        if ($interval > 0) {
            $baseResponse['interval'] = $interval;
            $baseResponse['min interval'] = $interval;
        }
        $baseResponse['warning message'] = $message;

        $this->response = $baseResponse;
    }

    public function getResponse(): array
    {
        return $this->response;
    }
}
