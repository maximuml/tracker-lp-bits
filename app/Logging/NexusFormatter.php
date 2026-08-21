<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger;
use Nexus\Nexus;

class NexusFormatter
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter($this->formatter());
            }
        }
    }

    protected function formatter(): LineFormatter
    {
        $id = 'NO_REQUEST_ID';
        if (Nexus::instance()) {
            $id = Nexus::instance()->getRequestId();
        }
        $format = '[%datetime%] ['.$id."] %channel%.%level_name%: %message% %context% %extra%\n";

        return tap(new LineFormatter($format, "Y-m-d\TH:i:s.vP", true, true), function ($formatter) {
            $formatter->includeStacktraces();
        });
    }
}
