<?php

declare(strict_types=1);

namespace App\Logging;

use App\Support\RequestContext;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger;

class NexusFormatter
{
    public function __invoke(IlluminateLogger $logger): void
    {
        $monolog = $logger->getLogger();
        if (! $monolog instanceof Logger) {
            return;
        }
        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter($this->formatter());
            }
        }
    }

    protected function formatter(): LineFormatter
    {
        $id = RequestContext::instance()->getRequestId();
        $format = '[%datetime%] ['.$id."] %channel%.%level_name%: %message% %context% %extra%\n";

        return tap(new LineFormatter($format, "Y-m-d\TH:i:s.vP", true, true), function ($formatter) {
            $formatter->includeStacktraces();
        });
    }
}
