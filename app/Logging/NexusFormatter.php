<?php

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Nexus\Nexus;

class NexusFormatter
{
    public function __invoke(IlluminateLogger $logger): void
    {
        $monolog = $logger->getLogger();
        foreach ($monolog->getHandlers() as $handler) {
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
